#!/usr/bin/env bash
# Sokin Pay release helper.
#
# Walks an engineer through the manual release flow documented in RELEASING.md.
# Subcommands:
#   prepare              Open a release PR for the next version (default).
#   publish vX.Y.Z       Fire the post-merge deploy workflows for an existing tag.
set -euo pipefail

REPO_SLUG="platacapital/sokin-woocommerce-plugin"
RELEASE_COMMIT_PREFIX="chore(release): v"

usage() {
  cat <<'EOF'
Usage: scripts/release.sh [prepare | publish vX.Y.Z]

  prepare              Open a release PR for the next version. Prompts for the
                       bump type and merchant-facing notes, runs the version
                       bump, and opens the PR.
  publish vX.Y.Z       Trigger Deploy to WordPress.org and Deploy Release Demo
                       for an existing GitHub Release.

With no arguments, runs `prepare`.
EOF
}

# ----- shared helpers -------------------------------------------------------

require() {
  local bin="$1"
  command -v "$bin" >/dev/null 2>&1 || {
    echo "error: required command '$bin' not found on PATH" >&2
    exit 1
  }
}

confirm() {
  local prompt="$1"
  local reply
  printf '%s [y/N] ' "$prompt"
  read -r reply
  [[ "$reply" =~ ^[Yy]$ ]]
}

repo_root() {
  git rev-parse --show-toplevel
}

# ----- prepare --------------------------------------------------------------

prepare_release() {
  require git
  require gh
  require node

  cd "$(repo_root)"

  if [[ -n "$(git status --porcelain)" ]]; then
    echo "error: working tree has uncommitted changes; commit or stash first" >&2
    exit 1
  fi

  echo "==> Syncing main"
  git fetch --tags --quiet origin
  git checkout main --quiet
  git pull --ff-only --quiet origin main

  local last_tag
  last_tag="$(git describe --tags --abbrev=0)"
  local current_version="${last_tag#v}"

  echo
  echo "Last released version: $current_version"
  echo "Commits since $last_tag (these will be in the release):"
  echo
  git log "$last_tag..HEAD" --no-merges --pretty='  - %s' || true
  echo

  local bump
  bump="$(prompt_bump)"

  local next_version
  next_version="$(compute_next_version "$current_version" "$bump")"
  echo
  echo "Next version: $next_version  ($bump bump from $current_version)"
  echo

  if ! confirm "Continue with v$next_version?"; then
    echo "Aborted." >&2
    exit 1
  fi

  local notes
  notes="$(collect_notes "$next_version")"

  echo
  echo "==> Cutting branch release/v$next_version"
  git checkout -b "release/v$next_version" --quiet

  echo "==> Running bump script"
  local b64
  b64="$(printf '%s' "$notes" | base64 | tr -d '\n')"
  node scripts/bump-wp-version.mjs "$next_version" "$b64"

  echo
  echo "==> Diff to be committed:"
  echo
  git --no-pager diff -- sokin-pay.php readme.txt
  echo

  if ! confirm "Commit and push this diff?"; then
    echo "Leaving branch in place for inspection. To abort: git checkout main && git branch -D release/v$next_version" >&2
    exit 1
  fi

  echo "==> Committing and pushing"
  local commit_body
  commit_body="$(git log "$last_tag..HEAD" --no-merges --pretty='* %s')"
  git add sokin-pay.php readme.txt
  if [[ -n "$commit_body" ]]; then
    git commit --quiet -m "${RELEASE_COMMIT_PREFIX}${next_version}" -m "$commit_body"
  else
    git commit --quiet -m "${RELEASE_COMMIT_PREFIX}${next_version}"
  fi
  git push --quiet -u origin "release/v$next_version"

  echo "==> Opening PR"
  local pr_url
  pr_url="$(
    gh pr create \
      --base main \
      --head "release/v$next_version" \
      --title "release: v$next_version" \
      --label release \
      --body "Prepares the release bump to v$next_version.

After merging, the Release workflow will tag and publish."
  )"

  print_prepare_next_steps "$next_version" "$pr_url"
}

prompt_bump() {
  echo "Pick a bump type." >&2
  echo "  1) patch  (default; for fix/perf only)" >&2
  echo "  2) minor  (new features)" >&2
  echo "  3) major  (breaking changes)" >&2
  printf 'Choice [1]: ' >&2
  local choice
  read -r choice
  case "${choice:-1}" in
    1|patch) echo patch ;;
    2|minor) echo minor ;;
    3|major) echo major ;;
    *) echo "error: invalid choice '$choice'" >&2; exit 1 ;;
  esac
}

compute_next_version() {
  local current="$1" bump="$2"
  local IFS='.'
  read -r major minor patch <<<"$current"
  : "${major:=0}" "${minor:=0}" "${patch:=0}"
  case "$bump" in
    major) major=$((major + 1)); minor=0; patch=0 ;;
    minor) minor=$((minor + 1)); patch=0 ;;
    patch) patch=$((patch + 1)) ;;
  esac
  echo "$major.$minor.$patch"
}

collect_notes() {
  local version="$1"
  local tmp
  tmp="$(mktemp -t "release-notes-v${version}.XXXXXX")"
  cat >"$tmp" <<EOF
# Release notes for v$version
#
# These notes go into:
#   - the WordPress.org changelog (readme.txt) - merchants will read this,
#   - the GitHub Release body, automatically.
#
# Write for store operators, not engineers. Mention any developer-visible
# changes (renamed hooks, new settings) explicitly. Otherwise a generic
# maintenance line is fine.
#
# Lines starting with '#' are ignored. Save and exit when done.

Routine maintenance update. No action required for merchants.
EOF
  "${EDITOR:-vi}" "$tmp"

  local cleaned
  cleaned="$(grep -v '^#' "$tmp" | sed -e '/./,$!d' | awk 'NR>0 {print} END{}')"
  rm -f "$tmp"

  if [[ -z "$(printf '%s' "$cleaned" | tr -d '[:space:]')" ]]; then
    echo "error: release notes are empty; aborting" >&2
    exit 1
  fi
  printf '%s\n' "$cleaned"
}

print_prepare_next_steps() {
  local version="$1" pr_url="$2"
  cat <<EOF

==============================================================================
Release PR opened: $pr_url

Next steps:

  1. Get the PR reviewed.
  2. Squash and merge. The squash subject MUST start with
        ${RELEASE_COMMIT_PREFIX}${version}
     GitHub's default '${RELEASE_COMMIT_PREFIX}${version} (#NN)' is fine.
  3. The Release workflow will then tag and publish v$version. Watch with:
        gh run watch -R $REPO_SLUG
  4. Once the GitHub Release exists, fire the deploys:
        scripts/release.sh publish v$version
==============================================================================
EOF
}

# ----- publish --------------------------------------------------------------

publish_release() {
  require gh
  cd "$(repo_root)"

  local tag="${1:-}"
  if [[ -z "$tag" ]]; then
    echo "error: publish requires a tag argument, e.g. v1.2.3" >&2
    exit 64
  fi
  if [[ ! "$tag" =~ ^v[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
    echo "error: tag '$tag' must look like vX.Y.Z" >&2
    exit 64
  fi

  if ! gh release view "$tag" --repo "$REPO_SLUG" >/dev/null 2>&1; then
    echo "error: GitHub Release $tag not found in $REPO_SLUG" >&2
    exit 1
  fi

  echo "==> Triggering Deploy to WordPress.org for $tag"
  gh workflow run deploy-wporg.yml --repo "$REPO_SLUG" -f "tag=$tag"

  echo "==> Triggering Deploy Release Demo for $tag"
  gh workflow run deploy-release.yaml --repo "$REPO_SLUG" -f "tag=$tag"

  cat <<EOF

Both deploys submitted. Watch progress with:
  gh run watch -R $REPO_SLUG

If 'Deploy to WordPress.org' fails because the SVN URL doesn't exist, the
plugin slug isn't yet approved on WordPress.org. See RELEASING.md for the
initial-submission flow.
EOF
}

# ----- dispatch -------------------------------------------------------------

main() {
  local cmd="${1:-prepare}"
  case "$cmd" in
    prepare) shift || true; prepare_release "$@" ;;
    publish) shift || true; publish_release "$@" ;;
    -h|--help|help) usage; exit 0 ;;
    *) usage; exit 64 ;;
  esac
}

main "$@"
