#!/usr/bin/env node

import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

// semantic-release is CommonJS, so access its default export via dynamic import
const semanticRelease = (await import('semantic-release')).default;

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

function readReleaserc() {
  const releasercPath = path.resolve(__dirname, '..', '.releaserc.json');
  const raw = fs.readFileSync(releasercPath, 'utf8');
  return JSON.parse(raw);
}

function buildPluginsConfig(releaserc) {
  let commitAnalyzerConfig = {};

  for (const plugin of releaserc.plugins || []) {
    if (Array.isArray(plugin) && plugin[0] === '@semantic-release/commit-analyzer') {
      commitAnalyzerConfig = plugin[1] || {};
      break;
    }
  }

  return [
    ['@semantic-release/commit-analyzer', commitAnalyzerConfig],
    '@semantic-release/release-notes-generator',
  ];
}

async function main() {
  const releaserc = readReleaserc();
  const branches = releaserc.branches || ['main'];
  const plugins = buildPluginsConfig(releaserc);

  const repositoryUrl =
    process.env.GITHUB_SERVER_URL && process.env.GITHUB_REPOSITORY
      ? `${process.env.GITHUB_SERVER_URL}/${process.env.GITHUB_REPOSITORY}`
      : undefined;

  const result = await semanticRelease({
    branches,
    repositoryUrl,
    plugins,
    dryRun: true,
    ci: false,
  });

  if (!result || !result.nextRelease) {
    console.error('No release is required according to semantic-release rules.');
    process.exit(1);
  }

  const { version, notes } = result.nextRelease;

  // Output as JSON so the workflow can parse it easily
  process.stdout.write(
    JSON.stringify(
      {
        version,
        notes,
      },
      null,
      2,
    ),
  );
}

main().catch((error) => {
  console.error('Failed to compute next version via semantic-release:', error);
  process.exit(1);
});


