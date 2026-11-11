#!/usr/bin/env bash
set -euo pipefail

# Run on Linux with Bash, flock, and coreutils. No real crontab is modified.
repo_dir="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)"
fixture_dir="$(mktemp -d)"
trap 'rm -rf "$fixture_dir"' EXIT
deployments_dir="$fixture_dir/deployments with 'quote"
mkdir -p "$fixture_dir/bin" "$deployments_dir/demo" "$deployments_dir/pr-42"
export TEST_CRONTAB="$fixture_dir/crontab"
export TEST_CRON_RUNS="$fixture_dir/runs"
export PATH="$fixture_dir/bin:$PATH"

cat > "$fixture_dir/bin/crontab" <<'SH'
#!/usr/bin/env bash
set -eu
if [ "${TEST_CRONTAB_ERROR:-0}" = 1 ]; then
  echo 'crontab: permission denied' >&2
  exit 1
fi
case "$1" in
  -l)
    if [ -f "$TEST_CRONTAB" ]; then
      cat "$TEST_CRONTAB"
    else
      echo 'no crontab for test-user' >&2
      exit 1
    fi
    ;;
  -) cat > "$TEST_CRONTAB" ;;
  *) exit 1 ;;
esac
SH
cat > "$fixture_dir/bin/docker-compose" <<'SH'
#!/usr/bin/env bash
set -eu
printf '%s\n' "$PWD" "$*" >> "$TEST_CRON_RUNS"
if [ -n "${TEST_RUN_GATE:-}" ]; then
  touch "$TEST_RUN_GATE.started"
  while [ ! -f "$TEST_RUN_GATE" ]; do sleep 0.05; done
fi
SH
cat > "$fixture_dir/bin/systemctl" <<'SH'
#!/usr/bin/env bash
exit "${TEST_CRON_SERVICE_DOWN:-0}"
SH
chmod +x "$fixture_dir/bin/"*
for environment in demo pr-42; do
  cp "$repo_dir/scripts/manage-wp-cron.sh" "$deployments_dir/$environment/"
done
demo_script="$deployments_dir/demo/manage-wp-cron.sh"
pr_script="$deployments_dir/pr-42/manage-wp-cron.sh"

# A fresh user's crontab can be installed and repeated installs converge.
bash "$demo_script" install
cp "$TEST_CRONTAB" "$fixture_dir/expected"
bash "$demo_script" install
cmp "$fixture_dir/expected" "$TEST_CRONTAB"
printf '0 2 * * * /usr/local/bin/unrelated-job\n' > "$TEST_CRONTAB"
bash "$demo_script" install &
demo_install=$!
bash "$pr_script" install &
pr_install=$!
wait "$demo_install" "$pr_install"
[ "$(grep -c 'sokin-wp-cron:' "$TEST_CRONTAB")" = 2 ]
grep -Fxq '0 2 * * * /usr/local/bin/unrelated-job' "$TEST_CRONTAB"
grep -q '^\* \* \* \* \* .* # sokin-wp-cron:demo$' "$TEST_CRONTAB"

# Execute the actual generated cron command, including path quoting and logging.
cron_command="$(sed -n 's/^\* \* \* \* \* //p' "$TEST_CRONTAB" | grep ' # sokin-wp-cron:demo$')"
/bin/sh -c "$cron_command"
printf '%s\n' "$deployments_dir/demo" 'exec -T wordpress php /var/www/html/wp-cron.php' > "$fixture_dir/expected-runs"
cmp "$fixture_dir/expected-runs" "$TEST_CRON_RUNS"

# A long running invocation must prevent another invocation from entering PHP.
export TEST_RUN_GATE="$fixture_dir/release-run"
bash "$demo_script" run "$fixture_dir/bin/docker-compose" &
cron_run=$!
timeout 10 bash -s -- "$TEST_RUN_GATE" <<'SH'
while [ ! -f "$1.started" ]; do sleep 0.05; done
SH
bash "$demo_script" run "$fixture_dir/bin/docker-compose"
[ "$(wc -l < "$TEST_CRON_RUNS")" = 4 ]
touch "$TEST_RUN_GATE"
wait "$cron_run"
unset TEST_RUN_GATE

# A failed crontab read must not replace existing jobs.
cp "$TEST_CRONTAB" "$fixture_dir/expected"
if TEST_CRONTAB_ERROR=1 bash "$demo_script" install 2>/dev/null; then
  echo 'Expected a failed crontab read to stop installation.' >&2
  exit 1
fi
cmp "$fixture_dir/expected" "$TEST_CRONTAB"

# Do not disable page-triggered cron when the host scheduler is stopped.
if TEST_CRON_SERVICE_DOWN=1 bash "$demo_script" install 2>/dev/null; then
  echo 'Expected a stopped cron service to stop installation.' >&2
  exit 1
fi
cmp "$fixture_dir/expected" "$TEST_CRONTAB"

# Teardown is repeatable and preserves the other environment and unrelated job.
bash "$pr_script" remove
bash "$pr_script" remove
[ "$(grep -c 'sokin-wp-cron:' "$TEST_CRONTAB")" = 1 ]
grep -q ' # sokin-wp-cron:demo$' "$TEST_CRONTAB"
bash "$demo_script" remove
grep -Fxq '0 2 * * * /usr/local/bin/unrelated-job' "$TEST_CRONTAB"
[ "$(wc -l < "$TEST_CRONTAB")" = 1 ]

echo 'PASS: cron installation, concurrent deployment, execution, locking, and teardown'
