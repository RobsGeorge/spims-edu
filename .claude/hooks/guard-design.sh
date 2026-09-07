#!/usr/bin/env bash
set -euo pipefail
input=$(cat)
path=$(jq -r '.tool_input.file_path // ""' <<<"$input")
content=$(jq -r '.tool_input.content // .tool_input.new_string // ""' <<<"$input")
[[ "$path" != *.blade.php ]] && exit 0

deny() {
  jq -n --arg r "$1" '{hookSpecificOutput:{hookEventName:"PreToolUse",
    permissionDecision:"deny", permissionDecisionReason:$r}}'
  exit 0
}

grep -q 'card border-0 shadow-sm' <<<"$content" && \
  deny "Banned: 'card border-0 shadow-sm'. Use <x-card variant=...> per docs/design-system.md."

grep -qE 'class="[^"]*\btext-muted\b' <<<"$content" && \
  deny "Banned: text-muted. Use --color-text-muted token class per docs/design-system.md."

grep -qE '\->value\s*\}\}' <<<"$content" && \
  deny "Banned: raw enum ->value in output. Map through a lang key or use <x-badge>."

grep -qE '(total|amount|price|balance|fee|cost)_minor\s*\}\}' <<<"$content" && \
  deny "Banned: raw minor units. Use <x-money :minor=... :currency=...> or Money::fromMinor()->format()."

grep -qE "style\s*=\s*[\"'][^\"']*color\s*:" <<<"$content" && \
  deny "Banned: inline color style. Use a token class from docs/design-system.md."

grep -qE '\bbg-light\b|\bbg-secondary\b' <<<"$content" && \
  deny "Banned: bg-light / bg-secondary. Use --color-* token classes."

exit 0
