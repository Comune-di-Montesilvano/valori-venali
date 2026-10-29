# CLAUDE.md — Valori Venali Aree Fabbricabili

Strumento web per il calcolo dei valori venali delle aree fabbricabili, ad uso dei Comuni italiani per l'accertamento IMU/ICI (DL 504/1992). Usa dati OMI dell'Agenzia delle Entrate.

## Stack

- **Backend:** PHP 8.5, Apache 2.4
- **Database:** MariaDB 11 (custom image con initdb embedded)
- **Frontend:** Bootstrap Italia (versione in `package.json`, **self-hosted**: installato via npm in uno stage del Dockerfile, servito da Apache su `/assets/bootstrap-italia` via `Alias` a `/opt/assets` — fuori dal DocumentRoot, così il bind mount di `src/` in dev non lo nasconde). Font locali via `window.__PUBLIC_PATH__` + `bootstrap.loadFonts()`. Nessun CDN/Google Fonts (GDPR: niente IP dei cittadini a terzi)
- **Infra:** Docker Compose (named volumes, no bind mount)
- **CI/CD:** GitHub Actions — lint + build + smoke test + Trivy + audit; Dependabot giornaliero con auto-merge patch/minor via merge queue; release firmate (cosign + SLSA/SBOM); `:latest` ricostruita solo su tag o aggiornamento dipendenze
- **Registry:** `ghcr.io/<owner>/valori-venali:latest`

## Avvio rapido

### Sviluppo (build locale)

```bash
cp .env.example .env
# Configura .env con i dati del comune e le credenziali
docker compose up -d --build
# App disponibile su http://localhost:<APP_PORT>
```

### Produzione (immagine prebuilt da GHCR)

Nel `docker-compose.yml`, sostituire il blocco `build:` del servizio `app` con:

```yaml
image: ghcr.io/comune-di-montesilvano/valori-venali:latest
```

### Comandi Docker utili

```bash
docker compose logs -f                                        # log in tempo reale
docker compose exec db mariadb -u vvenali -p valori_venali   # accesso MariaDB
docker compose down -v                                        # spegni e rimuovi volumi
docker compose pull                                           # aggiorna immagine GHCR
```

## Struttura del progetto

```
src/                        # Apache DocumentRoot
├── index.php               # Calcolatore pubblico (form + formula)
├── login.php / logout.php  # Autenticazione admin
├── robots.php / sitemap.php# SEO dinamico (rewrite via .htaccess)
├── includes/
│   ├── config.php          # Costanti app (DB, URL, comune, SEO)
│   ├── db.php              # Singleton PDO + helper query
│   └── auth.php            # Sessioni, CSRF, login, seedAdmin()
├── layout/
│   ├── header.php          # <head> + navbar Bootstrap Italia
│   └── footer.php          # Footer con versione
└── admin/                  # Area protetta (requireAuth())
    ├── dashboard.php        # KPI e statistiche
    ├── importa_omi.php      # Import CSV dati OMI (max 20MB)
    ├── parametri_omi.php    # Zone urbanistiche e coefficienti destinazione
    ├── coefficienti_abbattimento.php  # Coefficienti stato conservativo
    ├── fogli_omi.php        # Mapping fogli catastali → zone OMI
    └── backup.php           # Export/backup database
```

## Formula di calcolo

```
Valore Venale = Superficie (mq) × Valore OMI (€/mq) × Coeff. Destinazione × Coeff. Abbattimento
```

- **Valore OMI:** min, medio o max in base alla configurazione della zona
- **Montesilvano:** logica speciale → VMR = 20% del valore OMI, con deduzione opzionale costi adeguamento

## Database

| Tabella | Contenuto |
|---------|-----------|
| `valori_omi` | Valori OMI importati da CSV (Periodo, Zona, Tipologia, Stato, min/max) |
| `omi_abbattimenti` | Coefficienti stato conservativo (1.0 ottimo → 0.5 pessimo) |
| `omi_destinazione_urbanistica` | Zone urbanistiche PRG + coefficienti destinazione |
| `fogli_zone_omi` | Mapping fogli catastali → zone OMI |
| `users` | Account admin con password bcrypt |

Schema inizializzato automaticamente da `initdb/01_schema.sql`.

## Variabili d'ambiente (.env)

Le variabili critiche da impostare per ogni Comune:

| Variabile | Descrizione |
|-----------|-------------|
| `COMUNE_NOME` | Nome del Comune (usato in header, SEO, titolo pagine) |
| `COMUNE_PROVINCIA` | Sigla provincia |
| `APP_URL` | URL base dell'applicazione (canonical tag, redirect) |
| `APP_SECRET` | Chiave sessioni (min 32 caratteri) |
| `APP_ADMIN_USER` / `APP_ADMIN_PASS` | Credenziali admin (seedate automaticamente) |
| `DB_*` | Connessione MariaDB |

## Sicurezza

- Tutte le route admin protette da `requireAuth()` in `src/includes/auth.php`
- CSRF token su tutti i form POST (`verifyCsrf()` all'inizio di ogni handler POST — `impostazioni.php` ne era privo fino al 2026-10)
- Upload logo/favicon: estensione da allowlist + `getimagesize()`, mai l'estensione del client (prima: `logo.php` caricabile ed eseguibile). `/uploads` ha PHP spento (`conf-available/uploads.conf`), lo smoke test lo verifica
- Runtime: immagine `USER www-data`, Apache resta su **80** (sysctl `net.ipv4.ip_unprivileged_port_start=0`, default Docker ≥ 20.10, dichiarato nel compose). Compose: `read_only`, `cap_drop: ALL`, `no-new-privileges`, tmpfs `/tmp` `/var/run/apache2` `/var/lock/apache2`; DB con le 5 capability dell'entrypoint. Codice di proprietà root, scrivibile solo `uploads/`
- **Il compose su master è quello di produzione**: Portainer lo scarica da git in automatico, mentre `:latest` cambia solo su tag/Dependabot. Ogni modifica al compose deve funzionare **sia con l'immagine `:latest` attuale sia con la prossima** (2026-10: porta 8080 + `cap_drop: ALL` su master con `:latest` ancora root/80 avrebbe rotto la produzione). Esempio: `cap_add: [SETUID, SETGID]` è servito sull'app finché `:latest` era v2.4.x (master Apache root), tolto dopo v2.5.0 (prima release `USER www-data`)
- PDO prepared statements ovunque
- Cookie sessione: HttpOnly, SameSite=Strict, Secure se HTTPS
- Admin: `noindex, nofollow` via robots.php

## Task comuni

**Aggiornare dati OMI:** Admin → Importa OMI → upload CSV nel formato portale Sister
**Aggiungere zona urbanistica:** Admin → Parametri OMI
**Modificare coefficienti abbattimento:** Admin → Coefficienti Abbattimento
**Mappare fogli catastali:** Admin → Fogli OMI

## Infrastruttura Docker

| Servizio | Immagine | Note |
|---------|---------|------|
| `db` | Custom `docker/db/Dockerfile` | MariaDB 11 + `initdb/` embedded nell'immagine |
| `app` | Custom `docker/php/Dockerfile` | PHP 8.5-Apache, pubblicata su GHCR |

**Volumi:**
- `db_data` — dati MariaDB
- `app_code` — codice applicativo
- `app_uploads` — CSV e upload persistenti

Lo schema DB è incluso nell'immagine `db` (non serve bind mount su `initdb/`).

## CI/CD

**Dal 2026-10-28 allineata alla baseline di sicurezza di ComunicaPA** (`Comune-di-Montesilvano/ComunicaPA/docs/ci-cd-security-baseline.md`). Sostituisce lo split del 2026-09 (`publish-latest.yml` settimanale e `dependabot-update-branches.yml` rimossi).

**Regola di `:latest`** (decisione del maintainer): cambia **solo** con un nuovo tag `v*` oppure con aggiornamenti delle dipendenze (merge Dependabot, patch Debian, CVE con fix). Lavoro umano non taggato non va **mai** su `:latest`. Target = ultimo tag + il prefisso più lungo di soli commit `dependabot[bot]` che lo seguono su master (`--first-parent`); bump Dependabot arrivati dopo un commit umano → issue "Aggiornamenti Dependabot in attesa di tag", si sbloccano col tag. `APP_VERSION` = `vX.Y.Z` o `vX.Y.Z+deps.<sha>`. **I tag `v*` li crea solo l'umano.**

- **`test.yml`** — PR + `merge_group` + dispatch: `PHP Lint`, `Docker Build Test` (Trivy config bloccante, `.trivyignore` vuoto → build no push con `PKG_REFRESH` → smoke test `.github/scripts/smoke-test.sh` (porta/utente letti dall'immagine: le immagini non-root girano con le restrizioni del compose; controlla anche il corpo delle pagine — con `display_errors` un Fatal esce con 200 — il token CSRF, codice read-only e PHP bloccato in uploads) → Trivy image bloccante HIGH/CRITICAL con fix).
- **`security.yml`** — PR, coda, push master, giornaliero 05:00: `dependency-review` (solo PR, high), `npm-audit` (`--omit=dev --audit-level=high`), `trivy-fs` (vuln+secret+misconfig, SARIF `trivy-fs`).
- **`release.yml`** — tag `v*`, giornaliero 04:30 UTC, dispatch (`rebuild_latest`, `dry_run`). `setup` (tag → `vX.Y.Z` + `latest` se è il tag più alto; altrimenti `.github/scripts/rebuild-check.sh`: cause a revision di latest ≠ target, b `apt list --upgradable`, c Trivy HIGH/CRITICAL con fix; nessuna causa = skip; errore di verifica = causa) → `build-push` (push **solo per digest**, Trivy bloccante, SBOM CycloneDX, `attest-build-provenance` + `attest-sbom`, `cosign sign` keyless; cache registry `:buildcache`) → `smoke` (smoke-test sul digest, script da questo commit, initdb/compose dalla sorgente) → `publish` (`imagetools create`: tag solo ora). Rebuild automatico → `latest` + `latest-YYYYMMDD` (rollback). Causa c ancora bloccata → issue "Vulnerabilità non risolvibile con un rebuild".
- **`dependabot-automerge.yml`** — patch/minor `gh pr merge --auto` col token della GitHub App → merge queue; major → commento, resta aperta.
- **`security-alerts.yml`** — giornaliero 06:00 + push: alert CodeQL/Trivy high/critical aperti su master → run fallita + issue (non required).
- **`scorecard.yml`** (OpenSSF, pubblico), **`trivy-version-check.yml`** (lunedì: issue se esce un Trivy nuovo da ≥3 gg), **`ghcr-cleanup.yml`** (domenica: digest orfani, tiene 30 tag datati `latest-`/`weekly-`; dispatch = dry-run), **`publiccode-validate.yml`**.
- Verifica firma prima del deploy: `.github/scripts/verify-image.sh <tag>`.

**Regole per ogni workflow**: `permissions: contents: read` in testa, scritture solo nel job; action pinnate per SHA (`sha_pinning_required` sul repo); `persist-credentials: false`; primo step `step-security/harden-runner` (`audit` ovunque, `block` con `allowed-endpoints` nei job di `release.yml` — release fallita per "blocked" → host nel log del post-step, aggiungerlo se legittimo); `timeout-minutes`; Trivy a **versione fissa** (`v0.76.1`, in test/security/release — `version: latest` non più usato); input non fidati via `env:`.

**Dipendenze**: `dependabot.yml` giornaliero 01:00 Europe/Rome, gruppi minor/patch per ecosistema (docker `/docker/php`+`/docker/db`, docker-compose, npm, github-actions), cooldown 7gg (`semver-major-days: 14` **solo npm**: per docker/github-actions è invalido → Dependabot non parte, errore visibile solo su `/network/updates`). MariaDB major ignorate (restiamo su 11 LTS). Immagini base fissate per **digest** (Dockerfile e compose). Security updates (repo setting) senza cooldown.

**Merge queue**: master protetto da ruleset (PR obbligatoria, squash, merge queue, required check `PHP Lint`, `Docker Build Test`, `dependency-review`, `npm-audit`, `trivy-fs`, regola code scanning, nessun bypass). I required check devono girare su `merge_group`; un job saltato per `if:` vale verde, un job matrice saltato no. Mai `publiccode.yml validation` come required (path-filtered).

**GitHub App per le dipendenze**: permessi repo Contents RW, Pull requests RW, Workflows RW, installata solo su questo repo. Secret `DEPS_APP_CLIENT_ID` + `DEPS_APP_PRIVATE_KEY` **sia in Actions sia in Dependabot**.

**Impostazioni repo** (verifica come da baseline §1): token default `read`, secret scanning + push protection, PVR, CodeQL default setup (solo `actions`: PHP non supportato), allowed actions `selected` (contano anche quelle annidate, es. `aquasecurity/setup-trivy` dentro `trivy-action`: una nuova action di terze parti va aggiunta all'elenco o il workflow fallisce), solo squash, `delete_branch_on_merge`.

**Lezione 2026-10 (immagine stantia)**: con publish solo su tag, `:latest` aveva 260 CVE HIGH/CRITICAL correggibili mentre la base fresca ne aveva 0. Gli alert Dependabot **non** coprono i pacchetti OS dell'immagine e gli alert code-scanning non mandano email: l'unico avviso affidabile è un controllo schedulato che fallisce (ora `release.yml` causa b/c + `security-alerts.yml`).

**Storico bug**: su `pull_request` `GITHUB_REF` è `refs/pull/N/merge`, non `refs/heads/*` — da tenere a mente se si reintroduce logica branch-based. Con `format: sarif` trivy-action ignora `severity` senza `limit-severities-for-sarif: true`.

## Versione

Attuale: **2.5.0** (ultimo tag; il badge viene da `APP_VERSION` in build) — vedere `src/layout/footer.php` per il badge versione.
