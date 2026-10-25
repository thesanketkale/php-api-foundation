#!/usr/bin/env bash
# scripts/verify.sh: the single answer to "is this change OK?"
set -o pipefail
MODE="${1:-full}"
case "$MODE" in fast|full) ;; *) echo "usage: verify.sh [fast|full]" >&2; exit 64 ;; esac
ROOT="$(git rev-parse --show-toplevel 2>/dev/null || pwd)"
cd "$ROOT" || exit 1

# Ensure agent-team managed tools (~/.agent-team/bin) are in PATH
if [ -d "$HOME/.agent-team/bin" ]; then
  case ":$PATH:" in *":$HOME/.agent-team/bin:"*) ;; *) PATH="$HOME/.agent-team/bin:$PATH" ;; esac
fi

FAILED=""; SKIPPED=""; RAN=0
step() {
  local label="$1"; shift
  printf '\n> %s\n' "$label"; RAN=$((RAN + 1))
  if "$@"; then printf 'ok  %s\n' "$label"; else printf 'FAIL %s\n' "$label"; FAILED="$FAILED, $label"; fi
}
staged() { git diff --cached --name-only --diff-filter=ACMR -- "$@"; }
skip() {
  case "$SKIPPED," in *", $1,"*) return 0 ;; esac
  printf 'SKIPPED %s\n' "$1" >&2; SKIPPED="$SKIPPED, $1"
}
have() { if command -v "$1" >/dev/null 2>&1 || [ -x "$1" ]; then return 0; fi; skip "$1 (not installed)"; return 1; }

# ---------------------------------------------------------------- FAST ----
fast_checks() {
  php_syntax() {
    local bad=0
    while IFS= read -r f; do
      if [ -f "$f" ]; then
        php -l "$f" >/dev/null || bad=1
      fi
    done < <(find src public config db tests deploy -name '*.php' 2>/dev/null)
    return $bad
  }
  have php && step "php -l (all source files)" php_syntax
  have composer && [ -f "composer.json" ] && step "composer validate" composer validate --strict --no-check-all
  have vendor/bin/php-cs-fixer && step "php-cs-fixer" vendor/bin/php-cs-fixer fix --dry-run --diff --using-cache=no
}

# ---------------------------------------------------------------- FULL ----
full_checks() {
  have vendor/bin/phpstan && step "phpstan analyse" vendor/bin/phpstan analyse --no-progress --memory-limit=1G
  have vendor/bin/phpunit && step "phpunit test suite" vendor/bin/phpunit --no-coverage
  have composer && [ -f "composer.json" ] && step "composer audit" composer audit
}

fast_checks
if [ "$MODE" = "full" ]; then full_checks; fi

if [ -n "$FAILED" ]; then
  printf '\nverify %s: FAILED (%s)\n' "$MODE" "${FAILED#, }"
  exit 1
fi
if [ -n "$SKIPPED" ]; then
  printf '\nverify %s: INCOMPLETE. %d checks passed, NOT VERIFIED (skipped): %s\n' "$MODE" "$RAN" "${SKIPPED#, }"
  exit 0
fi
if [ "$RAN" -eq 0 ]; then
  printf '\nverify %s: NO CHECKS RAN. Nothing is configured yet; fill fast_checks/full_checks.\n' "$MODE"
  if [ "$MODE" = "full" ]; then exit 3; fi
  exit 0
fi
printf '\nverify %s: OK (%d checks)\n' "$MODE" "$RAN"
