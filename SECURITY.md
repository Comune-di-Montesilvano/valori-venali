# Security Policy

## Segnalazione di una vulnerabilità

Se scopri una vulnerabilità di sicurezza in Valori Venali, **non aprire una issue pubblica**.

Segnala privatamente via [GitHub Security Advisories](https://github.com/Comune-di-Montesilvano/valori-venali/security/advisories/new) — visibile solo ai maintainer finché non viene risolta e pubblicata.

In alternativa, contatta il Settore Informatica del Comune di Montesilvano:

- Email: supporto@comune.montesilvano.pe.it

## Versioni supportate

Solo l'ultima versione taggata (`vX.Y.Z`) riceve fix di sicurezza. Nessun supporto a versioni precedenti.

## Cosa aspettarsi

- Conferma di ricezione entro qualche giorno lavorativo.
- Nessun bug bounty: il progetto è software libero per la PA, non un prodotto commerciale.
- L'immagine Docker è scansionata con Trivy (config + vulnerabilità note) ad ogni build (bloccante su HIGH/CRITICAL con fix), risultati nella tab [Security](https://github.com/Comune-di-Montesilvano/valori-venali/security).

## Verifica dell'immagine

Le immagini su `ghcr.io/comune-di-montesilvano/valori-venali` sono firmate con cosign keyless da `release.yml` e hanno attestazioni GitHub di provenance (SLSA) e SBOM (CycloneDX). Prima di un deploy:

```sh
.github/scripts/verify-image.sh latest     # o un tag vX.Y.Z
```

Identità attesa: `https://github.com/Comune-di-Montesilvano/valori-venali/.github/workflows/release.yml@refs/(tags/v*|heads/master)`, issuer `https://token.actions.githubusercontent.com`.
