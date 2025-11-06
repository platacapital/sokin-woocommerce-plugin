import { readFile, writeFile } from 'node:fs/promises';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const projectRoot = path.resolve(__dirname, '..');

const newVersion = process.argv[2];
if (!newVersion) {
  console.error('Missing required version argument.');
  process.exit(1);
}

const encodedNotes = process.argv[3] ?? process.env.RELEASE_NOTES_B64 ?? '';
const releaseNotes = decodeReleaseNotes(encodedNotes).replace(/\r\n/g, '\n');

await updatePluginVersion(newVersion);
await updateReadme(newVersion, releaseNotes);

console.log(`Prepared WordPress assets for v${newVersion}`);

async function updatePluginVersion(version) {
  const pluginFile = path.join(projectRoot, 'sokinpay.php');
  let contents = await readFile(pluginFile, 'utf8');

  const headerRegex = /(\*\s*Version:\s*)([^\r\n]+)/;
  if (!headerRegex.test(contents)) {
    throw new Error('Unable to locate plugin header Version field in sokinpay.php');
  }
  contents = contents.replace(headerRegex, `$1${version}`);

  const constantRegex = /(define\('WOO_CUSTOM_PAYMENT',\s*')[^']+('\);)/;
  if (!constantRegex.test(contents)) {
    throw new Error('Unable to locate WOO_CUSTOM_PAYMENT constant in sokinpay.php');
  }
  contents = contents.replace(constantRegex, `$1${version}$2`);

  await writeFile(pluginFile, contents);
}

async function updateReadme(version, notes) {
  const readmePath = path.join(projectRoot, 'readme.txt');
  let contents = await readFile(readmePath, 'utf8');

  const versionRegex = /(Version:\s*)([^\r\n]+)/;
  if (!versionRegex.test(contents)) {
    throw new Error('Unable to locate Version field in readme.txt');
  }
  contents = contents.replace(versionRegex, `$1${version}`);

  const stableTagRegex = /(Stable tag:\s*)([^\r\n]+)/;
  if (!stableTagRegex.test(contents)) {
    throw new Error('Unable to locate Stable tag field in readme.txt');
  }
  contents = contents.replace(stableTagRegex, `$1${version}`);

  const changelogData = extractChangelog(contents);
  const formattedNotes = buildChangelogBody(notes);
  const entryForVersion = createChangelogEntry(version, formattedNotes);

  const filteredEntries = changelogData.entries.filter(
    (entry) => entry.version !== entryForVersion.version
  );
  filteredEntries.push(entryForVersion);
  filteredEntries.sort(sortEntriesDescending);

  const rebuiltChangelog = renderChangelog(filteredEntries);
  const updatedContents =
    changelogData.prefix + changelogData.heading + rebuiltChangelog;

  await writeFile(readmePath, ensureTrailingNewline(updatedContents));
}

function extractChangelog(contents) {
  const headingLabel = '== Changelog ==';
  const headingStart = contents.indexOf(headingLabel);
  if (headingStart === -1) {
    throw new Error('Unable to locate Changelog section in readme.txt');
  }

  const prefix = contents.slice(0, headingStart);
  const remainder = contents.slice(headingStart);
  const firstEntryIndex = remainder.indexOf('\n=');

  const headingEnd = firstEntryIndex === -1 ? remainder.length : firstEntryIndex + 1;
  const heading = remainder.slice(0, headingEnd);
  const body = remainder.slice(headingEnd);

  const entries = [];
  const entryRegex = /^=+\s*([^=\n]+?)\s*=+\s*\n([\s\S]*?)(?=^=+\s*[^=\n]+?\s*=+\s*\n|$)/gm;
  let match;
  let index = 0;
  while ((match = entryRegex.exec(body)) !== null) {
    const title = match[1].trim();
    const entryBody = match[2].trim();
    entries.push({
      title,
      version: extractVersionNumber(title),
      body: entryBody,
      originalIndex: index++
    });
  }

  return {
    prefix,
    heading,
    entries
  };
}

function buildChangelogBody(notes) {
  const lines = (notes || '')
    .split(/\r?\n/)
    .map((line) => line.trim())
    .filter(Boolean)
    .map((line) => {
      if (/^#+\s+/.test(line)) {
        return `* ${line.replace(/^#+\s+/, '')}`;
      }
      if (/^[-*]\s+/.test(line)) {
        return `* ${line.replace(/^[-*]\s+/, '')}`;
      }
      return `* ${line}`;
    });

  if (lines.length === 0) {
    lines.push('* Maintenance updates.');
  }

  return lines.join('\n');
}

function createChangelogEntry(version, notesBlock) {
  const releaseDate = new Date().toISOString().slice(0, 10);
  const bodyLines = [`* Released: ${releaseDate}`];
  if (notesBlock && notesBlock.trim().length > 0) {
    bodyLines.push(notesBlock.trim());
  }

  return {
    title: version,
    version,
    body: bodyLines.join('\n'),
    originalIndex: -1
  };
}

function sortEntriesDescending(a, b) {
  const comparison = compareSemver(a.version, b.version);
  if (comparison !== 0) {
    return -comparison;
  }
  return a.originalIndex - b.originalIndex;
}

function renderChangelog(entries) {
  if (entries.length === 0) {
    return '';
  }

  const rendered = entries.map((entry) => {
    const body = entry.body ? `${entry.body.trimEnd()}\n` : '';
    return `= ${entry.title} =\n${body}`.trimEnd();
  });

  return `${rendered.join('\n\n')}\n`;
}

function ensureTrailingNewline(text) {
  return text.endsWith('\n') ? text : `${text}\n`;
}

function extractVersionNumber(title) {
  const match = title.match(/\d+(?:\.\d+)*/);
  return match ? match[0] : title.trim();
}

function compareSemver(a, b) {
  const aParts = parseVersionParts(a);
  const bParts = parseVersionParts(b);
  const maxLength = Math.max(aParts.length, bParts.length);

  for (let i = 0; i < maxLength; i += 1) {
    const aValue = aParts[i] ?? 0;
    const bValue = bParts[i] ?? 0;
    if (aValue > bValue) {
      return 1;
    }
    if (aValue < bValue) {
      return -1;
    }
  }

  return 0;
}

function parseVersionParts(version) {
  const parts = version
    .split(/[.-]/)
    .map((part) => Number.parseInt(part, 10))
    .filter((part) => Number.isInteger(part));
  return parts.length > 0 ? parts : [0];
}

function decodeReleaseNotes(value) {
  if (!value) {
    return '';
  }

  try {
    return Buffer.from(value, 'base64').toString('utf8');
  } catch (error) {
    console.warn('Unable to decode release notes, falling back to raw value.');
    return value;
  }
}

