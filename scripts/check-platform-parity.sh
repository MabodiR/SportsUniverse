#!/usr/bin/env bash
set -euo pipefail

base_ref="${1:-}"
if [[ -z "$base_ref" ]]; then
  if [[ -n "${GITHUB_BASE_REF:-}" ]] && git rev-parse --verify "origin/$GITHUB_BASE_REF" >/dev/null 2>&1; then
    base_ref="origin/$GITHUB_BASE_REF"
  elif git rev-parse --verify HEAD^ >/dev/null 2>&1; then
    base_ref="HEAD^"
  else
    echo "Parity check skipped: no comparison commit is available."
    exit 0
  fi
fi

changed="$(git diff --name-only "$base_ref"...HEAD 2>/dev/null || git diff --name-only "$base_ref" HEAD)"
[[ -z "$changed" ]] && exit 0

web_changed=false
mobile_changed=false
if grep -Eq '^resources/js/(Pages|Components|Layouts)/' <<<"$changed"; then web_changed=true; fi
if grep -Eq '^mobile-app/(app|src)/' <<<"$changed"; then mobile_changed=true; fi

context="${PR_BODY:-} $(git log -1 --pretty=%B)"
if [[ "$web_changed" == true && "$mobile_changed" == false ]]; then
  if grep -Eqi '\[platform-web-only:[^]]+\]' <<<"$context"; then
    echo "Web-only platform exception documented."
  else
    echo "User-facing web files changed without corresponding mobile changes." >&2
    echo "Implement mobile parity or document [platform-web-only: reason]." >&2
    exit 1
  fi
fi
if [[ "$mobile_changed" == true && "$web_changed" == false ]]; then
  if grep -Eqi '\[platform-mobile-only:[^]]+\]' <<<"$context"; then
    echo "Mobile-only platform exception documented."
  else
    echo "User-facing mobile files changed without corresponding web changes." >&2
    echo "Implement web parity or document [platform-mobile-only: reason]." >&2
    exit 1
  fi
fi

echo "Platform parity check passed."
