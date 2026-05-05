import { readFile } from 'node:fs/promises';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

// Prints the WordPress.org changelog body for a given version on stdout.
// Used as semantic-release's `generateNotesCmd` (via @semantic-release/exec) so
// the GitHub Release body matches the merchant-facing notes already curated in
// readme.txt during the release-PR step. Single source of truth, no manual edit.

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const projectRoot = path.resolve(__dirname, '..');

const version = process.argv[2];
if (!version) {
  console.error('Missing required version argument.');
  process.exit(1);
}

const readmePath = path.join(projectRoot, 'readme.txt');
const contents = await readFile(readmePath, 'utf8');
const lines = contents.split(/\r?\n/);

const headingPattern = new RegExp(`^=+\\s*${escapeRegex(version)}\\s*=+\\s*$`);
const nextEntryPattern = /^=+\s*[^=\n]+?\s*=+\s*$/;

const startIdx = lines.findIndex((line) => headingPattern.test(line));
if (startIdx === -1) {
  console.error(`No changelog entry for version ${version} in readme.txt.`);
  process.exit(2);
}

const bodyLines = [];
for (let i = startIdx + 1; i < lines.length; i += 1) {
  if (nextEntryPattern.test(lines[i])) {
    break;
  }
  bodyLines.push(lines[i]);
}

const filtered = bodyLines
  .filter((line) => !/^\s*\*\s*Released:/i.test(line))
  .join('\n')
  .trim();

if (!filtered) {
  console.error(`Changelog entry for version ${version} is empty after filtering.`);
  process.exit(3);
}

process.stdout.write(`${filtered}\n`);

function escapeRegex(value) {
  return value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}
