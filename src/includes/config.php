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

// Determina la versione dell'app
$appVersion = getenv('APP_VERSION');
if (!$appVersion) {
    // Fallback per sviluppo locale: prova a leggere da Git se disponibile
    if (is_dir(ROOT_PATH . '/../.git')) {
        $gitVersion = @shell_exec('git describe --tags --always 2>/dev/null');
        $appVersion = ($gitVersion && trim($gitVersion)) ? trim($gitVersion) : 'dev-local';
    } else {
        $appVersion = 'dev-local';
    }
}
define('APP_VERSION', $appVersion);

// SEO
define('SEO_DESCRIPTION', 'Strumento istituzionale del ' . COMUNE_NOME . ' per il calcolo della stima dei valori venali delle aree fabbricabili ai fini IMU, basato sui dati ufficiali OMI dell\'Agenzia delle Entrate.');
define('SEO_KEYWORDS',    'valori venali, aree fabbricabili, calcolo IMU, OMI, ' . COMUNE_NOME . ', Agenzia delle Entrate, stima immobili, valore mercato');
