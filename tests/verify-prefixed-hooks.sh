#!/usr/bin/env bash
set -euo pipefail

gateway_path="${1:-includes/class-platasokin-wc-gateway.php}"

legacy_hooks=(
  "woocommerce_credit_card_form_start"
  "woocommerce_credit_card_form_end"
)

prefixed_hooks=(
  "platasokin_credit_card_form_start"
  "platasokin_credit_card_form_end"
)

for legacy_hook in "${legacy_hooks[@]}"; do
  if grep -Fq "do_action( '$legacy_hook'" "$gateway_path"; then
    echo "Found non-prefixed hook invocation: $legacy_hook" >&2
    exit 1
  fi
done

for prefixed_hook in "${prefixed_hooks[@]}"; do
  if ! grep -Fq "do_action( '$prefixed_hook'" "$gateway_path"; then
    echo "Missing prefixed hook invocation: $prefixed_hook" >&2
    exit 1
  fi
done
