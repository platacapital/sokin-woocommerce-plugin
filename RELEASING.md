# Releasing Sokin Pay

This guide describes how to cut a new release of the plugin, publish it to WordPress.org, and redeploy the demo. The process is currently manual end-to-end because the GitHub-Actions automation that opens the release PR is blocked by SRE policy and `semantic-release`'s downstream-trigger limitation. The runbook below is what the automation would have done.

## Overview

| Stage | Trigger | Workflow |
|---|---|---|
| 1. Open release PR | Manual (this guide) | _would be_ `prepare-release-pr.yml` |
| 2. Tag and create GitHub Release | Squash-merge of release PR with `chore(release): v…` subject | `release.yml` (runs `semantic-release`) |
| 3. Publish to WordPress.org | GitHub Release published, **or** manual button | `deploy-wporg.yml` |
| 4. Redeploy demo site | GitHub Release published, **or** manual button | `deploy-release.yaml` |

**Important.** Releases created by `semantic-release` use the default `GITHUB_TOKEN`. GitHub deliberately suppresses downstream workflow runs for events created by that token, so stages 3 and 4 do **not** auto-fire from stage 2. Use the manual buttons until the org issues a GitHub App token (or PAT) for the release workflow.

## Prerequisites

- Push access to `main` and the ability to merge release PRs.
- `gh` CLI authenticated against `platacapital/sokin-woocommerce-plugin`.
- Node.js 22.14.0 (matches the workflow). Only needed for running the bump script.

## 1. Cut the release PR

Pick the next version using [Conventional Commits](https://www.conventionalcommits.org/) rules from `.releaserc.json`:

- `fix:` / `perf:` → patch
- `feat:` → minor
- Any commit with `BREAKING CHANGE:` footer or `!` in the type → major
- `chore:`, `docs:`, `ci:`, `build:`, `refactor:`, `test:`, `style:` → no release on their own

Sanity-check the commits since the last tag:

```bash
git fetch origin --tags
git log "$(git describe --tags --abbrev=0)..origin/main" --no-merges --oneline
```

Cut the branch and run the bump script. The release notes you pass become both the WordPress.org changelog entry and (later) the GitHub Release body, so write them for **merchants**, not engineers. Mention any developer-visible changes (renamed hooks, new settings, etc.) explicitly. Otherwise a generic maintenance line is fine.

```bash
git checkout main && git pull --ff-only origin main
VERSION=1.2.3
git checkout -b "release/v$VERSION"

NOTES=$(cat <<'EOF'
Routine maintenance update. No action required for merchants.

Heads-up for developers: <only fill in if there is a real heads-up>.
EOF
)
B64=$(printf '%s' "$NOTES" | base64 | tr -d '\n')
node scripts/bump-wp-version.mjs "$VERSION" "$B64"
```

The script updates:

- `sokin-pay.php` plugin header `Version` and the `PLATASOKIN_VERSION` constant.
- `readme.txt` `Version`, `Stable tag`, and prepends a new entry under `== Changelog ==`.

Review, commit, push, open the PR. The commit subject must be exactly `chore(release): v$VERSION` — `release.yml` keys off this prefix.

```bash
git diff
git add sokin-pay.php readme.txt
git commit -m "chore(release): v$VERSION"
git push -u origin "release/v$VERSION"

gh pr create \
  --base main \
  --head "release/v$VERSION" \
  --title "release: v$VERSION" \
  --label release \
  --body "Prepares the release bump to v$VERSION.

After merging, the Release workflow will tag and publish."
```

## 2. Merge → tag → GitHub Release

Get the PR reviewed, then **squash and merge**. The squash subject must start with `chore(release): v`. Either of these work:

- `chore(release): v1.2.3`
- `chore(release): v1.2.3 (#42)` ← GitHub's default when squashing a labelled PR is fine; the trailing PR ref is allowed.

Optionally add a bullet-list of merged commits in the squash body, matching the style established by past releases:

```
* fix(area): brief description
* fix(area): brief description
* docs: ...
```

This is purely cosmetic for `git log`; the GitHub Release notes are generated separately.

After merge, watch the `Release` workflow:

```bash
gh run watch -R platacapital/sokin-woocommerce-plugin
```

It runs `semantic-release`, which creates the `v$VERSION` tag and the GitHub Release. **The auto-generated release body is dev-facing** (a list of conventional-commit subjects). Replace it with merchant-facing notes immediately:

```bash
gh release edit "v$VERSION" --notes "$(cat <<'EOF'
…same merchant-facing notes you used in step 1, formatted as Markdown…
EOF
)"
```

This matters because `deploy-wporg.yml` reads the release body and uses it to rebuild the WordPress.org `readme.txt` changelog before pushing to SVN. If you forget to edit it, dev-facing notes get published to the plugin directory.

## 3. Publish to WordPress.org

### Subsequent releases (after the slug is approved)

Run the manual button, since the release event won't auto-fire:

**Actions → Deploy to WordPress.org → Run workflow → tag: `v$VERSION`**

Or:

```bash
gh workflow run deploy-wporg.yml -f tag="v$VERSION"
```

The workflow:

1. Looks up the GitHub Release for that tag, reads its body.
2. Checks out the tagged code.
3. Re-runs `bump-wp-version.mjs` with the body so `readme.txt` matches the release notes.
4. Builds the distributable zip using `.distignore` as the deny list.
5. Attaches the zip to the GitHub Release (`gh release upload --clobber`).
6. Pushes to `plugins.svn.wordpress.org` via `10up/action-wordpress-plugin-deploy`.

### Initial submission (one time, for the WP.org review queue)

WordPress.org's review queue requires a clean zip submitted manually before the SVN repo exists. Build the same zip locally:

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

Submit the zip at https://wordpress.org/plugins/developers/add/. Once approved, the slug exists on `plugins.svn.wordpress.org` and the manual button above starts working.

## 4. Redeploy the demo

Same model — manual button:

**Actions → Deploy Release Demo → Run workflow → tag: `v$VERSION`**

Or:

```bash
gh workflow run deploy-release.yaml -f tag="v$VERSION"
```

Optional inputs (`wordpress_version`, `php_version`, `wp_cli_version`, `woocommerce_version`) default to the values pinned in the workflow `env`. Override per run if you need to validate against a specific stack.

The workflow builds an image from `tests/Dockerfile`, pushes to ECR, and SSHes into the demo host to redeploy via `docker-compose`. End state: https://demo.wordpress.sokin.com runs `v$VERSION`.

## Troubleshooting

**`Release` workflow didn't run after I merged the PR.** The squash commit subject didn't start with `chore(release): v`. Check `git log -1 main --format=%s`. Fix-forward by pushing a no-op commit with the correct subject, or by editing the merge commit (only if not yet pushed downstream).

**`Deploy to WordPress.org` fails with `svn: E170000: URL '…' doesn't exist`.** The plugin slug isn't yet approved on WordPress.org. Use the initial-submission flow in step 3 to push to the review queue.

**`10up/action-wordpress-plugin-deploy@…` fails to resolve.** The action does not publish a floating major tag (`@v2`). It is pinned by SHA in `deploy-wporg.yml`; if you bump it, resolve the new SHA via `gh api repos/10up/action-wordpress-plugin-deploy/git/refs/tags/<tag>` and pin with the version in a trailing comment.

**Demo deploy or WP.org deploy did not auto-fire after a release.** Expected — see the note about `GITHUB_TOKEN` at the top. Run the manual button.

**Plugin Check / WP.org review flags dev files in the zip** (`pnpm-lock.yaml`, `CONTRIBUTING.md`, etc.). Tighten `.distignore`. Rebuild the local zip per step 3 to verify before resubmitting.

## Future automation

When the org issues a GitHub App token (or PAT) for releases, set it as `GITHUB_TOKEN` for the `npx semantic-release` step in `release.yml`. Releases created by an App or PAT **do** fire downstream `release` events, which makes stages 3 and 4 fully automatic. The manual buttons stay as a recovery path.

A second improvement is extending `prepare-release-pr.yml` (currently blocked by SRE) to populate the squash commit body with a bullet list derived from `git log "$LAST_TAG..HEAD" --no-merges --pretty='* %s'`, matching the body style we use today by hand. Once SRE allows actions to open PRs, this automates step 1 entirely.
