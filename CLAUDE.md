# CLAUDE.md — Valori Venali Aree Fabbricabili

Strumento web per il calcolo dei valori venali delle aree fabbricabili, ad uso dei Comuni italiani per l'accertamento IMU/ICI (DL 504/1992). Usa dati OMI dell'Agenzia delle Entrate.

## Stack

- **Backend:** PHP 8.2, Apache 2.4
- **Database:** MariaDB 11 (custom image con initdb embedded)
- **Frontend:** Bootstrap Italia v2.13.0 (CDN)
- **Infra:** Docker Compose (named volumes, no bind mount)
- **CI/CD:** GitHub Actions — lint PHP + build & push su GHCR
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
| `app` | Custom `docker/php/Dockerfile` | PHP 8.2-Apache, pubblicata su GHCR |

**Volumi:**
- `db_data` — dati MariaDB
- `app_code` — codice applicativo
- `app_uploads` — CSV e upload persistenti

Lo schema DB è incluso nell'immagine `db` (non serve bind mount su `initdb/`).

## CI/CD

`.github/workflows/ci.yml` esegue su push/PR a `master`:
1. **PHP Lint** — syntax check su tutti i `src/*.php`
2. **Build & Push GHCR** — pubblica `ghcr.io/<owner>/valori-venali:latest` e il tag SHA

Richiede `packages: write` per il push sul registry.

## Versione

Attuale: **2.1.0** — vedere `src/layout/footer.php` per il badge versione.
