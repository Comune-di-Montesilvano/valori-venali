# Contribuire

Segnalazioni e proposte sono benvenute: apri una [issue](https://github.com/Comune-di-Montesilvano/valori-venali/issues) per bug e richieste, una pull request per le modifiche. Le vulnerabilità **non** vanno in issue pubbliche: vedi [SECURITY.md](SECURITY.md).

## Ambiente di sviluppo

```bash
cp .env.example .env                 # imposta credenziali e dati del Comune
docker compose up -d --build         # app su http://localhost:<APP_PORT>
composer install                     # strumenti di test (PHP 8.5)
composer test                        # PHPUnit
composer stan                        # PHPStan (livello 8)
```

## Regole per le pull request

- Una PR per argomento, verso `master`. Il merge avviene in squash tramite merge queue, dopo che tutti i controlli richiesti sono verdi: `PHP Lint`, `PHP Tests`, `Docker Build Test` (build + smoke test + Trivy), `dependency-review`, `npm-audit`, `trivy-fs`, `semgrep`.
- **Test**: ogni modifica alla formula di calcolo (`src/includes/calcolo.php`) o al parser CSV OMI (`src/includes/omi_csv.php`) va accompagnata da test in `tests/`. Le nuove funzionalità con logica non banale vanno estratte in funzioni pure e testate.
- **Codice**: PHP 8.5, nessun warning PHPStan livello 8, nessuna deprecation. Query solo con prepared statement (`DB::query` / `DB::queryOne`), output HTML sempre con `htmlspecialchars`, `verifyCsrf()` all'inizio di ogni handler POST, route admin protette da `requireAuth()`.
- **Semgrep**: un falso positivo si marca nel codice con `// nosemgrep: <rule-id>` e la motivazione accanto; mai disattivare una regola per tutto il progetto.
- **Dipendenze**: versioni fissate (lockfile, digest delle immagini, SHA delle Action). Gli aggiornamenti arrivano da Dependabot.
- Messaggi di commit nel formato [Conventional Commits](https://www.conventionalcommits.org/) (`feat:`, `fix:`, `chore:`, …).

## Rilasci

I tag `vX.Y.Z` (semver) li crea solo il maintainer; `release.yml` costruisce, firma e pubblica l'immagine e la GitHub Release. Le note di rilascio sono generate dalle PR.

## Licenza

Contribuendo accetti che il tuo contributo sia distribuito con la licenza del progetto, [EUPL-1.2](LICENSE).
