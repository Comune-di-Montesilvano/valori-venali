<?php
/**
 * Configurazione applicazione — legge variabili d'ambiente Docker
 */

// Percorso root
define('ROOT_PATH', dirname(__DIR__));

define('DB_HOST',   getenv('DB_HOST')   ?: 'db');
define('DB_PORT',   getenv('DB_PORT')   ?: '3306');
define('DB_NAME',   getenv('DB_NAME')   ?: 'valori_venali');
define('DB_USER',   getenv('DB_USER')   ?: 'vvenali');
define('DB_PASS',   getenv('DB_PASS')   ?: '');

define('APP_URL',    getenv('APP_URL')   ?: 'http://localhost:8080');
define('APP_SECRET', getenv('APP_SECRET') ?: 'default_insecure_secret_change_me');

define('COMUNE_NOME',      getenv('COMUNE_NOME')      ?: 'Comune');
define('COMUNE_PROVINCIA', getenv('COMUNE_PROVINCIA') ?: '');

// Configurazione Versione e Link
define('GITHUB_URL',  getenv('GITHUB_URL')  ?: 'https://github.com/Comune-di-Montesilvano/valori-venali');

// Determina la versione dell'app e la revisione (per i link GitHub)
$appVersion   = getenv('APP_VERSION');
$appRevision  = getenv('APP_REVISION');
$appIsTag     = filter_var(getenv('APP_IS_TAG'), FILTER_VALIDATE_BOOLEAN);

if (!$appVersion) {
    // Fallback per sviluppo locale: prova a leggere da Git se disponibile
    if (is_dir(ROOT_PATH . '/../.git')) {
        $gitTag = @shell_exec('git describe --tags --exact-match 2>/dev/null');
        if ($gitTag) {
            $appVersion  = trim($gitTag);
            $appRevision = trim($gitTag);
            $appIsTag    = true;
        } else {
            $gitBranch   = @shell_exec('git rev-parse --abbrev-ref HEAD 2>/dev/null');
            $gitSha      = @shell_exec('git rev-parse --short HEAD 2>/dev/null');
            $appVersion  = trim($gitBranch) . '-' . trim($gitSha);
            $appRevision = @shell_exec('git rev-parse HEAD 2>/dev/null'); // Full SHA for links
            $appIsTag    = false;
        }
    }
}

define('APP_VERSION',  $appVersion  ?: 'dev-local');
define('APP_REVISION', trim($appRevision) ?: 'master');
define('APP_IS_TAG',   $appIsTag);

// SEO
define('SEO_DESCRIPTION', 'Strumento istituzionale del ' . COMUNE_NOME . ' per il calcolo della stima dei valori venali delle aree fabbricabili ai fini IMU, basato sui dati ufficiali OMI dell\'Agenzia delle Entrate.');
define('SEO_KEYWORDS',    'valori venali, aree fabbricabili, calcolo IMU, OMI, ' . COMUNE_NOME . ', Agenzia delle Entrate, stima immobili, valore mercato');
