## Cosa cambia

<!-- Descrizione breve del cambiamento e perché serve -->

## Come è stato testato

<!-- Comandi eseguiti, ambiente (locale/CI), esito -->

## Checklist

- [ ] CI verde su questa PR (PHP Lint, Docker Build Test con smoke test, controlli di sicurezza)
- [ ] Provato in locale con `docker compose up -d --build`
- [ ] Se ho toccato lo schema DB, ho aggiornato `initdb/` (e previsto la migrazione per le istanze esistenti)
- [ ] Se ho toccato `package.json`, ho aggiornato `package-lock.json`
- [ ] Nessun segreto, dato personale o `.env` nel diff
