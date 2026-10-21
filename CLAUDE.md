# CLAUDE.md — Valori Venali Aree Fabbricabili

Strumento web per il calcolo dei valori venali delle aree fabbricabili, ad uso dei Comuni italiani per l'accertamento IMU/ICI (DL 504/1992). Usa dati OMI dell'Agenzia delle Entrate.

## Stack

- **Backend:** PHP 8.4, Apache 2.4
- **Database:** MariaDB 11 (custom image con initdb embedded)
- **Frontend:** Bootstrap Italia (versione in `package.json`, **self-hosted**: installato via npm in uno stage del Dockerfile, servito da Apache su `/assets/bootstrap-italia` via `Alias` a `/opt/assets` — fuori dal DocumentRoot, così il bind mount di `src/` in dev non lo nasconde). Font locali via `window.__PUBLIC_PATH__` + `bootstrap.loadFonts()`. Nessun CDN/Google Fonts (GDPR: niente IP dei cittadini a terzi)
- **Infra:** Docker Compose (named volumes, no bind mount)
- **CI/CD:** GitHub Actions — lint + build + smoke test + Trivy; Dependabot con auto-merge patch/minor; `:latest` ripubblicata ogni settimana
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
- CSRF token su tutti i form POST
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
| `app` | Custom `docker/php/Dockerfile` | PHP 8.4-Apache, pubblicata su GHCR |

**Volumi:**
- `db_data` — dati MariaDB
- `app_code` — codice applicativo
- `app_uploads` — CSV e upload persistenti

Lo schema DB è incluso nell'immagine `db` (non serve bind mount su `initdb/`).

## CI/CD

**Dal 2026-09-13, split in due workflow** (pattern standard riusato tra i progetti, rif. canonico `gopulley`), sostituendo il precedente `ci.yml` monolitico:

- **`.github/workflows/test.yml`** — push/PR a `master` + `workflow_dispatch`:
  1. **PHP Lint** — syntax check su tutti i `src/*.php` (PHP 8.4)
  2. **Docker Build Test** — Trivy config scan del Dockerfile (`scan-type: config`, bloccante — `.trivyignore` a root, `DS-0002` ignorato con giustificazione: base `php:8.4-apache` gira il master come root by design, i worker reali sono `www-data` via `APACHE_RUN_USER` interno) + build **senza push** (`pull: true`) + **smoke test** (`.github/scripts/smoke-test.sh`: MariaDB con `initdb/` + app, 200 su home/`admin/login.php`/asset/font, niente errori PHP nei log) + **Trivy image scan bloccante** (HIGH/CRITICAL con fix disponibile).
- **`.github/workflows/release.yml`** — **solo** su tag `v*` push + `workflow_dispatch` (input `tag`): build e push su `ghcr.io/<owner>/valori-venali` (tag versione + `latest` + SHA), poi Trivy image scan (report-only, SARIF su tab Security). **I tag `v*` li crea solo l'umano.**
- **`.github/workflows/publish-latest.yml`** — cron **martedì 06:00 UTC** + dispatch: rebuild `no-cache`/`pull` → smoke test → Trivy bloccante → push **solo** `:latest` + `:weekly-YYYYMMDD` (rollback). Mai tag `v*`. Sorgente **ibrida**: se dopo l'ultimo `v*` su master ci sono solo commit `dependabot[bot]` → build da master (`APP_VERSION=vX.Y.Z+deps.<sha>`); se c'è lavoro umano non rilasciato → build dall'ultimo tag (solo patch base). Lo script di smoke viene da master, i dati (initdb, compose) dalla sorgente; i check asset scattano solo se la sorgente ha `package.json` (tag ≤ v2.3.4 usano il CDN). Fallimento = mail "workflow failed" = intervento manuale.
- **`.github/workflows/dependabot-automerge.yml`** — su PR Dependabot (`opened`/`reopened`): `dependabot/fetch-metadata` → patch/minor `gh pr merge --auto --squash` (merge appena passano i required check); major → nessun merge + commento, la PR aperta è la segnalazione. Richiede repo setting `allow_auto_merge: true`. L'auto-merge è abilitato col **token della GitHub App** (non `GITHUB_TOKEN`): un merge fatto da `GITHUB_TOKEN` non genera eventi `push` → il workflow sotto non partirebbe.
- **`.github/workflows/dependabot-update-branches.yml`** — su push a master (+ cron ogni 6h come paracadute): con il token dell'App fa `update-branch` della PR Dependabot **più vecchia** in stato `BEHIND` con auto-merge attivo. Serve perché master ha `strict: true` e Dependabot rebasa solo sui conflitti; un push fatto con `GITHUB_TOKEN` non farebbe ripartire la CI. Una PR alla volta: merge → push su master → PR successiva.

**GitHub App per le dipendenze**: permessi repo Contents RW, Pull requests RW, Workflows RW (le PR delle Action toccano `.github/workflows`), installata solo su questo repo. Secret `DEPS_APP_CLIENT_ID` + `DEPS_APP_PRIVATE_KEY` **sia in Actions sia in Dependabot** (i workflow lanciati da Dependabot vedono solo i secret Dependabot). Effetto collaterale: dopo un `update-branch` dell'app, Dependabot considera la PR "modificata da altri" e non la rebasa più da sola; sui conflitti serve `@dependabot recreate`.

**Ciclo settimanale**: lunedì Dependabot (docker `/docker/php`+`/docker/db`, docker-compose, npm, github-actions; cooldown 7gg) → auto-merge patch/minor → martedì `publish-latest` ripubblica `:latest`. I Comuni con `docker compose pull` prendono le patch senza che serva un tag.

Richiede `packages: write` per il push sul registry (in `release.yml`).

**Baseline sicurezza**: Trivy config + image scan bloccanti (test/publish), report-only in release, tutte le Action pinnate per commit SHA, `dependabot.yml` — ecosistemi `docker` (`/docker/php`, `/docker/db`) + `docker-compose` + `npm` + `github-actions`, cooldown 7gg (`default-days` only: `semver-major-days` NON supportato per docker/github-actions — config invalido = Dependabot non parte mai, errore visibile solo su `/network/updates`). `master` è protetto: required check `PHP Lint`+`Docker Build Test` (nomi job invariati dallo split, branch protection non toccata; mai `publiccode.yml validation`, path-filtered — bloccherebbe le PR che non toccano quel file), no force-push, no delete.

**Dependabot ha 2 switch indipendenti**: `dependabot.yml` (version updates, file-based) e "security updates" automatiche (repo setting, non nel file — verifica/attiva con `gh api repos/<owner>/valori-venali/vulnerability-alerts` [204=on] e `gh api -X PUT repos/<owner>/valori-venali/automated-security-fixes`). Il file presente non implica il secondo attivo.

**Lezione 2026-10 (immagine stantia)**: con publish solo su tag, `:latest` su GHCR resta ferma all'ultima release — a ottobre aveva 260 CVE HIGH/CRITICAL correggibili (apache2, openssl, perl, kernel headers) mentre la base fresca ne aveva 0. Gli alert Dependabot **non** coprono i pacchetti OS dell'immagine (qui nessun manifest → sempre `[]`), e gli alert code-scanning di Trivy non mandano email: l'unico avviso affidabile è lo scan schedulato che fallisce. Rimedio allora: nuovo tag di release; ora: `publish-latest.yml` settimanale.

**Storico bug (nel vecchio `ci.yml`, principio ancora valido)**: `docker-build` pushava su GHCR anche dalle pull_request (login+push incondizionati) — fix: `push`/login condizionati a `github.event_name == 'push'` (ora superato: in `test.yml` il push è sempre `false`, in `release.yml` sempre `true` perché gira solo su tag/dispatch). Bug correlato: su `pull_request` `GITHUB_REF` è `refs/pull/N/merge`, non `refs/heads/*` — lo strip prefisso lasciava lo slash nel tag Docker (`invalid reference format`); da tenere a mente se si reintroduce logica branch-based.

**`aquasecurity/trivy-action` — serve `version: latest` esplicito**, il binario Trivy pinnato di default da alcune release dell'action non installa (stesso gotcha già preso su ComunicaPA/ProntoPA).

## Versione

Attuale: **2.3.4** (ultimo tag; il badge viene da `APP_VERSION` in build) — vedere `src/layout/footer.php` per il badge versione.
