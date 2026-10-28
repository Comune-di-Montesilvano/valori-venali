#!/usr/bin/env bash
# Rebuild giornaliero di :latest (release.yml, schedule e dry_run).
#
# Target di :latest = ultimo tag vX.Y.Z + il prefisso più lungo di soli commit
# Dependabot che lo seguono su master. Lavoro umano non taggato non va MAI su
# :latest; i bump Dependabot arrivati dopo un commit umano restano "in attesa
# di tag" (pending_deps, release.yml apre una issue).
#
# Cause di rebuild, controllate sull'immagine :latest attuale (per digest):
#   a. la label org.opencontainers.image.revision è diversa dal target
#      (nuovo merge Dependabot, o label assente);
#   b. patch Debian disponibili (`apt list --upgradable` dentro l'immagine);
#   c. Trivy (DB aggiornato): vulnerabilità HIGH/CRITICAL con fix.
# Nessuna causa → skip=true. Un controllo che non riesce a verificare conta
# come causa (fallire chiusi = ricostruire): mai uno skip per un errore.
#
# Output (GITHUB_OUTPUT): skip, reason, target, version, pending_deps, cause_c.
#   IMAGE=ghcr.io/<owner>/valori-venali MAIN_REF=origin/master .github/scripts/rebuild-check.sh
# Richiede docker, git (storia e tag completi), jq, trivy.
set -euo pipefail

IMAGE="${IMAGE:?IMAGE non impostata}"
MAIN_REF="${MAIN_REF:-origin/master}"
OUT="${GITHUB_OUTPUT:-/dev/null}"
SUMMARY="${GITHUB_STEP_SUMMARY:-/dev/null}"
# Stesse regole della scansione bloccante di release.yml.
TRIVY_ARGS=(image --quiet --skip-db-update --scanners vuln --ignore-unfixed --format json
  --ignorefile .trivyignore)

# Primi 5 elementi (uno per riga) separati da virgola, poi "+N".
short() { awk 'NF && ++n <= 5 { printf "%s%s", (n > 1 ? ", " : ""), $0 } END { if (n > 5) printf ", +%d", n - 5 }'; }
count() { awk 'NF { n++ } END { print n + 0 }'; }

# --- Target -----------------------------------------------------------------
last_tag=$(git describe --tags --abbrev=0 --match 'v[0-9]*' "$MAIN_REF")
tag_commit=$(git rev-list -n1 "$last_tag")
target=$tag_commit
human=0
pending=0
while read -r sha author; do
  if [[ "$author" == "dependabot[bot]" ]]; then
    if ((human == 0)); then target=$sha; else pending=$((pending + 1)); fi
  else
    human=$((human + 1))
  fi
done < <(git log --reverse --first-parent --format='%H %an' "$last_tag..$MAIN_REF")

if [[ "$target" == "$tag_commit" ]]; then
  version=$last_tag
else
  version="$last_tag+deps.$(git rev-parse --short "$target")"
fi

# --- Cause ------------------------------------------------------------------
db_ok=true
trivy image --download-db-only --quiet || db_ok=false

a=no b=no c=no cause_c=false
ref="$IMAGE:latest"
if ! docker pull -q "$IMAGE:latest" >/dev/null; then
  a="latest assente o non leggibile"
  b="—"
  c="—"
else
  # Per digest: Trivy e i container lavorano sull'immagine esatta di latest.
  ref=$(docker inspect --format '{{range .RepoDigests}}{{println .}}{{end}}' "$IMAGE:latest" | grep -m1 "^$IMAGE@")
  rev=$(docker inspect --format '{{index .Config.Labels "org.opencontainers.image.revision"}}' "$IMAGE:latest")

  # a. Revision di latest diversa dal target.
  if [[ -z "$rev" || "$rev" == unknown ]]; then
    a="label revision assente"
  elif [[ "$rev" != "$target" ]]; then
    a="revision ${rev:0:7} ≠ target ${target:0:7}"
  fi

  # b. Patch Debian. Root solo nel container usa e getta: l'indice va scritto.
  if list=$(docker run --rm --user 0 --entrypoint sh "$ref" -c \
      'apt-get update -qq >/dev/null && apt list --upgradable 2>/dev/null'); then
    pkgs=$(grep -F 'upgradable from' <<<"$list" | cut -d/ -f1 || true)
    n=$(count <<<"$pkgs")
    if ((n > 0)); then b="$n pacchetti aggiornabili ($(short <<<"$pkgs"))"; fi
  else
    b="errore aggiornamento indice apt"
  fi

  # c. Vulnerabilità HIGH/CRITICAL con fix.
  if ! $db_ok; then
    c="errore download DB Trivy"
  elif ids=$(trivy "${TRIVY_ARGS[@]}" --severity HIGH,CRITICAL "$ref" \
      | jq -r '[.Results[]?.Vulnerabilities[]?.VulnerabilityID] | unique | .[]'); then
    n=$(count <<<"$ids")
    if ((n > 0)); then
      c="$n HIGH/CRITICAL con fix ($(short <<<"$ids"))"
      cause_c=true
    fi
  else
    c="errore Trivy"
  fi
fi

causes=()
cells=()
for k in a b c; do
  case $k in a) v=$a ;; b) v=$b ;; c) v=$c ;; esac
  if [[ "$v" == no ]]; then
    cells+=("✅ no")
  elif [[ "$v" == "—" ]]; then
    cells+=("—")
  else
    causes+=("$k: $v")
    [[ "$v" == errore* || "$v" == *assente* ]] && cells+=("⚠️ $v") || cells+=("🔁 $v")
  fi
done

skip=true
reason=
if ((${#causes[@]})); then
  skip=false
  reason=$(printf '%s; ' "${causes[@]}")
  reason=${reason%; }
fi

{
  echo "skip=$skip"
  echo "reason=$reason"
  echo "target=$target"
  echo "version=$version"
  echo "pending_deps=$pending"
  echo "cause_c=$cause_c"
} >>"$OUT"

{
  echo "## Rebuild di latest: controllo cause"
  echo
  echo "Ultimo tag: \`$last_tag\` · target: \`${target:0:7}\` (\`$version\`) · master: \`$(git rev-parse --short "$MAIN_REF")\`"
  if ((human > 0)); then
    echo
    echo "$human commit non Dependabot dopo \`$last_tag\`: non entrano in latest senza un nuovo tag."
  fi
  if ((pending > 0)); then
    echo
    echo "⏳ $pending commit Dependabot dopo lavoro non rilasciato: in attesa di tag."
  fi
  echo
  echo "| Immagine (latest) | a. revision ≠ target | b. patch Debian | c. Trivy HIGH/CRITICAL con fix |"
  echo "|---|---|---|---|"
  echo "| \`${ref##*@}\` | ${cells[0]} | ${cells[1]} | ${cells[2]} |"
  echo
  if $skip; then
    echo "**Esito: skip** — nessuna causa, \`latest\` resta invariato."
  else
    echo "**Esito: rebuild** — $reason"
  fi
} | tee -a "$SUMMARY"
