#!/usr/bin/env bash
set -euo pipefail

# Deployment copies this script beside docker-compose.yml on the host.
deploy_dir="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
script_path="$deploy_dir/$(basename -- "${BASH_SOURCE[0]}")"
marker="# sokin-wp-cron:$(basename -- "$deploy_dir")"

case "${1:-}" in
  run)
    # Keep the lock in the parent shell until PHP finishes, even for long jobs.
    exec 9>"$deploy_dir/.wp-cron.lock"
    flock -n 9 || exit 0
    cd -- "$deploy_dir"
    "${2:?Missing Compose executable}" exec -T wordpress php /var/www/html/wp-cron.php
    exit
    ;;
  install|remove) ;;
  *) echo "Usage: $0 install|remove|run [compose-executable]" >&2; exit 1 ;;
esac

for dependency in crontab flock; do
  command -v "$dependency" >/dev/null || { echo "Host command required: $dependency" >&2; exit 1; }
done

if [ "$1" = install ]; then
  if command -v systemctl >/dev/null && ! systemctl is-active --quiet cron && ! systemctl is-active --quiet crond; then
    echo 'The host cron or crond service must be running before disabling WP-Cron spawning.' >&2
    exit 1
  fi
  compose_path="$(command -v docker-compose)"
  # Quote paths for cron's /bin/sh, including deployment paths with spaces.
  quote() { printf "'%s'" "${1//\'/\'\\\'\'}"; }
  cron_entry="* * * * * /bin/bash $(quote "$script_path") run $(quote "$compose_path") >> $(quote "$deploy_dir/wp-cron.log") 2>&1 $marker"
  # Cron interprets percent signs even inside shell quotes.
  cron_entry="${cron_entry//%/\\%}"
fi

# All environments share this user's crontab. Serialize their read/update/write.
exec 9>"$(dirname -- "$deploy_dir")/.wp-crontab.lock"
flock 9
if existing_cron="$(LC_ALL=C crontab -l 2>&1)"; then
  :
elif [[ "$existing_cron" == "no crontab for "* ]]; then
  existing_cron=""
else
  printf '%s\n' "$existing_cron" >&2
  exit 1
fi

{
  if [ -n "$existing_cron" ]; then
    while IFS= read -r line; do
      if [[ "$line" != *" $marker" ]]; then
        printf '%s\n' "$line"
      fi
    done <<< "$existing_cron"
  fi
  if [ "$1" = install ]; then
    printf '%s\n' "$cron_entry"
  fi
} | crontab -
