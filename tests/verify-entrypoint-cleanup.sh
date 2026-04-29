#!/usr/bin/env bash
set -euo pipefail

entrypoint_path="${1:-tests/docker-entrypoint.sh}"

legacy_plugin_files=(
  "includes/class_woo_cpay_loader.php"
  "includes/class_woo_cpay_woo_functions.php"
)

for legacy_plugin_file in "${legacy_plugin_files[@]}"; do
  if ! grep -Fq "$legacy_plugin_file" "$entrypoint_path"; then
    echo "Missing cleanup for legacy plugin file: $legacy_plugin_file" >&2
    exit 1
  fi
done

cleanup_line="$(
  awk '/LEGACY_PLUGIN_FILES=/ { print NR; exit }' "$entrypoint_path"
)"
plugin_check_line="$(
  awk '/PLUGIN_CHECK_SLUG=/ { print NR; exit }' "$entrypoint_path"
)"

if [ -z "$cleanup_line" ] || [ -z "$plugin_check_line" ]; then
  echo "Could not find cleanup and Plugin Check provisioning blocks." >&2
  exit 1
fi

if [ "$cleanup_line" -ge "$plugin_check_line" ]; then
  echo "Legacy file cleanup must run before Plugin Check provisioning." >&2
  exit 1
fi
