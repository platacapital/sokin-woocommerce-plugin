#!/usr/bin/env bash
set -euo pipefail

# Runs the PHPUnit suite in tests/phpunit, installing dependencies first when
# vendor/ is absent. Uses a local PHP toolchain when one is on PATH and falls
# back to Docker otherwise. Arguments are forwarded to PHPUnit:
#   tests/run-phpunit.sh --testdox --filter refused

repo_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$repo_root"

php_image="${SOKIN_PHP_IMAGE:-php:8.3-cli}"
composer_image="${SOKIN_COMPOSER_IMAGE:-composer:2}"

if command -v php >/dev/null 2>&1 && command -v composer >/dev/null 2>&1; then
  runtime=local
elif command -v docker >/dev/null 2>&1; then
  runtime=docker
else
  echo "Need either php and composer on PATH, or docker." >&2
  exit 1
fi

# Matching the host UID keeps vendor/ and .phpunit.result.cache from being
# written into the working tree as root.
docker_run() {
  docker run --rm \
    --volume "$repo_root:/app" \
    --workdir /app \
    --user "$(id -u):$(id -g)" \
    "$@"
}

if [[ ! -x vendor/bin/phpunit ]]; then
  echo "Installing PHP dependencies..." >&2
  if [[ "$runtime" == local ]]; then
    composer install --no-interaction
  else
    # composer:2 defaults to a home directory this UID cannot write to.
    docker_run --env COMPOSER_HOME=/tmp/composer "$composer_image" install --no-interaction
  fi
fi

if [[ "$runtime" == local ]]; then
  exec vendor/bin/phpunit "$@"
fi

docker_run "$php_image" php vendor/bin/phpunit "$@"
