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
- L'immagine Docker è scansionata con Trivy (config + vulnerabilità note) ad ogni build, risultati nella tab [Security](https://github.com/Comune-di-Montesilvano/valori-venali/security).
