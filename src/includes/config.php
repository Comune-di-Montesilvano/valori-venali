<?php
/**
 * Configurazione applicazione — legge variabili d'ambiente Docker
 */

define('DB_HOST',   getenv('DB_HOST')   ?: 'db');
define('DB_PORT',   getenv('DB_PORT')   ?: '3306');
define('DB_NAME',   getenv('DB_NAME')   ?: 'valori_venali');
define('DB_USER',   getenv('DB_USER')   ?: 'vvenali');
define('DB_PASS',   getenv('DB_PASS')   ?: '');

define('APP_URL',    getenv('APP_URL')   ?: 'http://localhost:8080');
define('APP_SECRET', getenv('APP_SECRET') ?: 'default_insecure_secret_change_me');

define('COMUNE_NOME',      getenv('COMUNE_NOME')      ?: 'Comune');
define('COMUNE_PROVINCIA', getenv('COMUNE_PROVINCIA') ?: '');

// Versione app
define('APP_VERSION', getenv('APP_VERSION') ?: '2.2.0');
define('GITHUB_URL',  'https://github.com/mirkochipdotcom/valori-venali');

// SEO
define('SEO_DESCRIPTION', 'Strumento istituzionale del ' . COMUNE_NOME . ' per il calcolo della stima dei valori venali delle aree fabbricabili ai fini IMU, basato sui dati ufficiali OMI dell\'Agenzia delle Entrate.');
define('SEO_KEYWORDS',    'valori venali, aree fabbricabili, calcolo IMU, OMI, ' . COMUNE_NOME . ', Agenzia delle Entrate, stima immobili, valore mercato');

// Percorso root
define('ROOT_PATH', dirname(__DIR__));
