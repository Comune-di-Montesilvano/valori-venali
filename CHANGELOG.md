# Changelog

Modifiche rilevanti per chi installa o aggiorna l'applicativo. Ogni versione indica cosa cambia e **l'impatto dell'aggiornamento**. Formato [Keep a Changelog](https://keepachangelog.com/it/1.1.0/), versioni [Semantic Versioning](https://semver.org/lang/it/). L'elenco completo delle PR è in ogni [GitHub Release](https://github.com/Comune-di-Montesilvano/valori-venali/releases).

<!-- Prima di creare un tag vX.Y.Z aggiungere qui la sezione "## [vX.Y.Z] - AAAA-MM-GG":
     release.yml si ferma se manca e la usa come note della GitHub Release. -->

## [v2.6.0] - 2026-11-02

### Impatto dell'aggiornamento

- **API JSON (`api_omi.php`)**: il valore OMI medio non è più arrotondato all'intero, come già nel calcolatore. Esempio: `VALORE_BASE_OMI` da 1273 a 1272,5, `VALORE` da 186,70 a 186,62 €/m². Cambia solo per le zone configurate sul valore medio, con min+max dispari. Chi integra l'API deve aspettarsi differenze di pochi centesimi.
- Nessuna modifica al database né al `.env` obbligatoria. Il calcolatore web dà gli stessi risultati della v2.5.0.

### Novità

- API: parametro opzionale `oneri_adattamento=1` (deduzione del 5%, come nel calcolatore) e campo `ONERI_ADATTAMENTO` nella risposta. API documentata nel README.
- Verifica della firma dell'immagine all'avvio dello stack: `VERIFY_IMAGES=true` nel `.env` (default spento). Se la firma non è valida l'app non parte.
- GitHub Release con SBOM e attestazioni firmate (provenance SLSA e SBOM) verificabili offline.

### Correzioni

- Import CSV OMI: un'intestazione senza anno salvava un periodo vuoto, ora "Sconosciuto".
- Calcolatore e API usano la stessa formula (`src/includes/calcolo.php`): prima l'API ne aveva una copia con risultati diversi.

### Sicurezza e qualità

- Test automatici (PHPUnit) della formula e del parser CSV, anche con input casuali; analisi statica PHPStan livello 8.
- Analisi di sicurezza del codice con Semgrep su ogni modifica; escaping esplicito dei valori in pagina.

## [v2.5.0] - 2026-10-28

### Impatto dell'aggiornamento

- Il container gira come utente non privilegiato (`www-data`) con filesystem in sola lettura: usare il `docker-compose.yml` aggiornato (porta interna sempre 80). Eventuali file scritti fuori da `uploads/` non sono più possibili.
- Nessuna modifica al database.

### Correzioni

- Compatibilità PHP 8.5: niente più avvisi di deprecazione (parametri OMI, import CSV); gli errori PHP non vengono più mostrati agli utenti.
- Impostazioni: protezione CSRF sul form e upload di logo/favicon limitato a immagini valide.

### Sicurezza

- Immagini firmate (cosign) con provenance e SBOM, ricostruite automaticamente quando escono patch di sicurezza.
