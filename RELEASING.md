# Releasing Sokin Pay

The release flow is currently manual end-to-end because the GitHub-Actions automation that opens the release PR is blocked by SRE policy and `semantic-release`'s downstream-trigger limitation. `scripts/release.sh` walks you through it.

```bash
scripts/release.sh prepare   # opens the release PR
# (review, squash-merge, the Release workflow tags and publishes)
scripts/release.sh publish v1.2.3   # fires the post-merge deploys
```

If anything fails, see the **[Failure modes](#failure-modes)** section.

## Prerequisites

- Push access to `main` and the ability to merge release PRs.
- `gh` CLI authenticated against `platacapital/sokin-woocommerce-plugin`.
- Node.js 22.14.0 (matches the workflow). Used by the bump script.
- A clean working tree on a checkout that can fast-forward `main`.

## What the script does

### `prepare`

1. Verifies the working tree is clean and syncs `main` to the remote.
2. Shows commits since the last tag and prompts for the bump type (patch/minor/major).
3. Opens `$EDITOR` for the merchant-facing release notes. Write for store operators, not engineers; mention any developer-visible changes (renamed hooks, new settings) explicitly. Otherwise a generic maintenance line is fine.
4. Cuts a `release/v$VERSION` branch and runs `scripts/bump-wp-version.mjs`, which updates the plugin header `Version`, the `PLATASOKIN_VERSION` constant, and prepends a new `= $VERSION =` block to the `readme.txt` changelog.
5. Shows the diff, asks for confirmation, then commits with the gating subject `chore(release): v$VERSION`, pushes, and opens the PR.

The merchant-facing notes you wrote in step 3 become both the WordPress.org changelog entry and (after the Release workflow runs) the GitHub Release body, via `scripts/extract-readme-notes.mjs` wired into `.releaserc.json`. No manual editing of the GitHub Release is required.

### Merge

Squash-merge the PR. The squash subject must start with `chore(release): v` — `release.yml` keys off this prefix. GitHub's default `chore(release): v$VERSION (#NN)` is fine; the trailing PR ref is allowed.

After merge, watch the `Release` workflow:

```bash
gh run watch -R platacapital/sokin-woocommerce-plugin
```

It runs `semantic-release`, which creates the `v$VERSION` tag and the GitHub Release.

### `publish`

Releases created by `semantic-release` use the default `GITHUB_TOKEN`. GitHub deliberately suppresses downstream workflow runs for events created by that token, so `deploy-wporg.yml` and `deploy-release.yaml` do not auto-fire. `scripts/release.sh publish v$VERSION` triggers both manually using `gh workflow run`. They build the dist zip, attach it to the GitHub Release, push to `plugins.svn.wordpress.org`, and redeploy `demo.wordpress.sokin.com`.

## Initial WordPress.org submission

The first time you publish a plugin slug, WordPress.org's review queue requires a clean zip submitted manually before the SVN repo exists. Build the same zip the workflow would build:

```bash
WORKTREE=/tmp/sokin-pay-build
rm -rf "$WORKTREE"
git worktree add --detach "$WORKTREE" "v$VERSION"
cd "$WORKTREE"
rsync -rc --delete --exclude-from=.distignore ./ ./dist/
cd dist
zip -r "/tmp/sokin-pay-v$VERSION.zip" . -x '*.DS_Store'
cd - && git worktree remove "$WORKTREE"
ls -lh "/tmp/sokin-pay-v$VERSION.zip"
```

Submit the zip at https://wordpress.org/plugins/developers/add/. Once approved, the slug exists on `plugins.svn.wordpress.org` and `scripts/release.sh publish` works end-to-end.

## Failure modes

**`Release` workflow didn't run after I merged the PR.** The squash commit subject didn't start with `chore(release): v`. Check `git log -1 main --format=%s`. Fix-forward by pushing a no-op commit with the correct subject.

**`Deploy to WordPress.org` fails with `svn: E170000: URL '…' doesn't exist`.** The plugin slug isn't yet approved on WordPress.org. Use the initial-submission flow above.

**`10up/action-wordpress-plugin-deploy@…` fails to resolve.** The action does not publish a floating major tag. It is pinned by SHA in `deploy-wporg.yml`; if you bump it, resolve the new SHA via `gh api repos/10up/action-wordpress-plugin-deploy/git/refs/tags/<tag>` and pin with the version in a trailing comment.

**`Deploy Release Demo` hangs at `Deploy via SSH`.** The EC2 host is unhealthy — out of memory, docker daemon stuck, or a previous compose run holding a lock. SSH in, inspect (`docker ps`, `docker info`, `free -m`, `df -h`), recover, and re-trigger.

**Plugin Check / WP.org review flags dev files in the zip** (`pnpm-lock.yaml`, `CONTRIBUTING.md`, etc.). Tighten `.distignore` and rebuild the local zip before resubmitting.

## Future automation

When the org issues a GitHub App token (or PAT) for releases, set it as `GITHUB_TOKEN` for the `npx semantic-release` step in `release.yml`. Releases created by an App or PAT do fire downstream `release` events, which makes the `publish` step automatic. The script stays as a recovery path.

`prepare-release-pr.yml` is also kept up to date with the script: when SRE allows the bot to open PRs, dispatching the workflow will produce the same release PR (same `chore(release): v…` subject, same bullet-list commit body) the script produces locally. Until then, the script is the canonical entry point.
