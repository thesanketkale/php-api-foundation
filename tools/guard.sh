#!/usr/bin/env bash
# tools/guard.sh: Clean-room and secret hygiene scanner
set -e

FORBIDDEN=("vuexr" "trium" "fudge3d" "BEGIN OPENSSH PRIVATE KEY" "BEGIN RSA PRIVATE KEY" "BEGIN EC PRIVATE KEY" "BEGIN PRIVATE KEY")
ROOT="$(git rev-parse --show-toplevel 2>/dev/null || pwd)"
cd "$ROOT"

FAILED=0

for term in "${FORBIDDEN[@]}"; do
  # Grep tracked files ignoring case
  MATCHES=$(git grep -i "$term" -- ':!tools/guard.*' ':!AGENTS.md' 2>/dev/null || true)
  if [ -n "$MATCHES" ]; then
    echo "Clean-room violation: matched '$term':"
    echo "$MATCHES"
    FAILED=1
  fi
done

if [ $FAILED -ne 0 ]; then
  echo "Guard scan FAILED."
  exit 1
fi

echo "Guard scan PASSED. Clean-room integrity verified."
exit 0
