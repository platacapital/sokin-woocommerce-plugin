#!/usr/bin/env bash
set -euo pipefail

repo_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
container="${1:-sokin-woocommerce-plugin-wordpress-1}"
report="${2:-$(mktemp "${TMPDIR:-/tmp}/sokin-plugin-check-report.XXXXXX")}"
staging="$(mktemp -d "${TMPDIR:-/tmp}/sokin-plugin-check.XXXXXX")"

cleanup() {
  rm -rf "$staging"
}
trap cleanup EXIT

if docker exec "$container" wp plugin is-installed plugin-check; then
  docker exec "$container" wp plugin update plugin-check
else
  docker exec "$container" wp plugin install plugin-check --activate
fi
docker exec "$container" wp plugin get plugin-check --field=version

mkdir "$staging/sokin-checkout"
rsync -rc --exclude-from="$repo_root/.distignore" "$repo_root/" "$staging/sokin-checkout/"

# The submission form rejects this field even if Plugin Check accepts it.
if sed -n '1,/^[[:space:]]*\*\//p' "$staging/sokin-checkout/sokin-checkout.php" | grep -Eiq '^[[:space:]]*\*[[:space:]]*Tested up to:'; then
  echo 'Remove "Tested up to" from the PHP plugin header; keep it only in readme.txt.' >&2
  exit 1
fi
# Runtime checks require the tested plugin to be installed and active. A
# separate container mounts the release files over that plugin's directory.
docker exec "$container" wp plugin is-active sokin-checkout
plugins_dir="$(docker exec "$container" wp eval 'echo WP_PLUGIN_DIR;')"
image="$(docker inspect --format '{{.Config.Image}}' "$container")"
network="$(docker inspect --format '{{range $name, $network := .NetworkSettings.Networks}}{{$name}}{{"\n"}}{{end}}' "$container" | head -n 1)"
checker_cli="$plugins_dir/plugin-check/cli.php"
check_status=0
docker run --rm --volumes-from "$container" --network "$network" \
  --mount "type=bind,source=$staging/sokin-checkout,target=$plugins_dir/sokin-checkout,readonly" \
  --entrypoint wp "$image" plugin check sokin-checkout \
  --slug=sokin-checkout \
  --require="$checker_cli" \
  --format=strict-json \
  --include-experimental \
  --include-low-severity-errors \
  --include-low-severity-warnings > "$report" 2> "$report.stderr" || check_status=$?

cat "$report"
cat "$report.stderr" >&2
printf '\nReport: %s\n' "$report"

# Plugin Check 2.1 prints this message only when no errors or warnings exist.
# Its exit status alone does not distinguish findings from a clean result.
if (( check_status != 0 )) || [[ "$(cat "$report")" != 'Success: Checks complete. No errors found.' ]]; then
  exit 1
fi
