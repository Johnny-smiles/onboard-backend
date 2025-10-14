#!/usr/bin/env bash
# upgrade_orchestrator.sh — executes fenced ```bash blocks in a plan file (e.g., UPGRADE_PLAN.md)
set -euo pipefail
PLAN="${1:-}"; [[ -z "$PLAN" ]] && { echo "Usage: ./upgrade_orchestrator.sh UPGRADE_PLAN.md"; exit 1; }
INSIDE=0; LANG=""; TMP="$(mktemp)"; IDX=0
exec_block(){ local f="$1"; echo "=== Running bash block #$IDX ==="; bash -euxo pipefail "$f"; }
while IFS='' read -r line || [[ -n "$line" ]]; do
  if [[ "$INSIDE" -eq 0 && "$line" =~ ^\`\`\`([a-zA-Z0-9_-]+) ]]; then LANG="${BASH_REMATCH[1]}"; INSIDE=1; : > "$TMP"; continue; fi
  if [[ "$INSIDE" -eq 1 && "$line" == '```' ]]; then
    if [[ "$LANG" == "bash" ]]; then IDX=$((IDX+1)); exec_block "$TMP"; fi
    INSIDE=0; LANG=""; : > "$TMP"; continue
  fi
  if [[ "$INSIDE" -eq 1 ]]; then printf "%s\n" "$line" >> "$TMP"; fi
done < "$PLAN"
echo "All bash blocks executed."
