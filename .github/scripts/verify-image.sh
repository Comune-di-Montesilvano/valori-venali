#!/usr/bin/env sh
# Verifica, prima del deploy, che l'immagine di un tag sia quella prodotta da
# release.yml di questo repo: firma cosign keyless e, se `gh` è disponibile,
# attestazioni GitHub (provenance SLSA e SBOM). Stampa il digest verificato.
#
#   .github/scripts/verify-image.sh v2.5.0          # o latest
#   CONTAINER_CLI=podman .github/scripts/verify-image.sh latest
#
# Non serve cosign installato: gira nell'immagine ufficiale, fissata per digest.
# Esce con errore se la verifica fallisce: in quel caso NON aggiornare lo stack.
set -eu

TAG="${1:?uso: $0 <tag immagine, es. v2.5.0 o latest>}"
CLI="${CONTAINER_CLI:-docker}"
REPO="Comune-di-Montesilvano/valori-venali"
IMAGE="ghcr.io/comune-di-montesilvano/valori-venali"
COSIGN="ghcr.io/sigstore/cosign/cosign:v3.1.4@sha256:0f06154836d2de34f887a5c273506d52ca43edaa1d9e53e6510fa78a48f69c2d"
# Solo firme fatte da release.yml di questo repo, su un tag v* o sul rebuild da master.
IDENTITY="^https://github.com/${REPO}/\.github/workflows/release\.yml@refs/(tags/v.*|heads/master)$"
ISSUER="https://token.actions.githubusercontent.com"

ref="${IMAGE}:${TAG}"
# La verifica risolve il tag in digest e verifica quello: il digest stampato è quello firmato.
out=$("$CLI" run --rm "$COSIGN" verify \
  --certificate-identity-regexp "$IDENTITY" \
  --certificate-oidc-issuer "$ISSUER" \
  --output json "$ref" 2>/dev/null) || {
  echo "ERRORE: firma non valida o assente per $ref" >&2
  exit 1
}
digest=$(printf '%s' "$out" | grep -oE '"docker-manifest-digest":"sha256:[0-9a-f]{64}"' | head -n1 | cut -d'"' -f4)
[ -n "$digest" ] || { echo "ERRORE: digest non ricavato per $ref" >&2; exit 1; }

if command -v gh >/dev/null 2>&1; then
  for type in https://slsa.dev/provenance/v1 https://cyclonedx.org/bom; do
    gh attestation verify "oci://${IMAGE}@${digest}" -R "$REPO" \
      --signer-workflow "${REPO}/.github/workflows/release.yml" \
      --predicate-type "$type" >/dev/null || {
      echo "ERRORE: attestazione $type assente o non valida per $ref@$digest" >&2
      exit 1
    }
  done
else
  echo "AVVISO: gh non trovato, verificata solo la firma cosign (niente provenance/SBOM)." >&2
fi
echo "OK  ${ref}@${digest}"
