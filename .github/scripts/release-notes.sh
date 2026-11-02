#!/usr/bin/env bash
# Stampa la sezione "## [<tag>]" di CHANGELOG.md (senza l'intestazione), cioè
# le note di rilascio scritte per gli utenti. Esce con errore se la sezione
# manca o è vuota: release.yml la richiede per ogni tag.
#
#   .github/scripts/release-notes.sh v2.6.0 [CHANGELOG.md]
set -euo pipefail

TAG="${1:?uso: $0 <tag vX.Y.Z> [changelog]}"
FILE="${2:-CHANGELOG.md}"

notes=$(awk -v tag="$TAG" '
  /^## \[/ { if (found) exit; found = index($0, "## [" tag "]") == 1; next }
  found
' "$FILE" | sed -e '/./,$!d')

if [[ -z "${notes//[[:space:]]/}" ]]; then
  echo "ERRORE: nessuna sezione \"## [$TAG]\" in $FILE: scrivere le note di rilascio prima del tag" >&2
  exit 1
fi
printf '%s\n' "$notes"
