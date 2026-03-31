<?php
/**
 * API JSON per esporre i Valori OMI di un determinato foglio catastale
 * (Mantienendo la retrocompatibilità col precedente formato applicativo)
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/settings.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Content-Type: application/json; charset=utf-8');

$foglio = isset($_GET['foglio']) ? trim($_GET['foglio']) : null;

if (!$foglio) {
    http_response_code(400);
    echo json_encode(['error' => 'Parametro foglio mancante. Esempio: ?foglio=10']);
    exit;
}

// 1. Trova zona OMI
$foglio_row = DB::queryOne('SELECT zona_omi FROM fogli_zone_omi WHERE foglio_catastale = ?', [$foglio]);

if (!$foglio_row) {
    echo json_encode(['error' => 'Foglio non trovato', 'foglio' => $foglio]);
    exit;
}

$zona_omi = $foglio_row['zona_omi'];

// 2. Parametri globali da database (con fallback a 1 se non presenti)
$id_destinazione = Settings::get('OMI_ID_COFFICIENTE_DESTINAZIONE', 1);
$id_abbattimento = Settings::get('OMI_ID_COEFFICIENTE_ABBATTIMENTO', 1);

// 3. Trova destinazione urbanistica
$dati_destinazione = DB::queryOne('SELECT * FROM omi_destinazione_urbanistica WHERE id_destinazione = ?', [$id_destinazione]);

if (!$dati_destinazione) {
    // Fallback: se in settings l'ID configurato non esiste, proviamo a prendere la prima destinazione disponibile
    $dati_destinazione = DB::queryOne('SELECT * FROM omi_destinazione_urbanistica ORDER BY id_destinazione ASC LIMIT 1');
}

if (!$dati_destinazione) {
    $coefficiente_destinazione = 1;
    $tipo_valore               = 2;
    $cod_tip                   = '';
    $stato                     = '';
} else {
    $coefficiente_destinazione = (float) $dati_destinazione['coefficiente_destinazione'];
    $tipo_valore               = (int) $dati_destinazione['Valore'];
    $cod_tip                   = $dati_destinazione['Cod_Tip'];
    $stato                     = $dati_destinazione['Stato'];
}

// 4. Trova i dati OMI filtrando per zona, tipologia e stato
$dati_omi = DB::queryOne(
    'SELECT * FROM valori_omi WHERE Zona = ? AND Cod_Tip = ? AND Stato = ? ORDER BY Periodo DESC LIMIT 1',
    [$zona_omi, $cod_tip, $stato]
);

// Fallback: se la combo Cod_Tip + Stato originaria del setting (o del primo ID) non esiste in questa zona, prendiamo il primo valore omi normale della zona
if (!$dati_omi) {
    $dati_omi = DB::queryOne(
        'SELECT * FROM valori_omi WHERE Zona = ? ORDER BY Periodo DESC, Compr_max DESC LIMIT 1',
        [$zona_omi]
    );
}

if (!$dati_omi) {
    echo json_encode(0);
    exit;
}

// 5. Trova coefficiente di abbattimento
$dati_coefficiente = DB::queryOne('SELECT * FROM omi_abbattimenti WHERE id_coefficiente = ?', [$id_abbattimento]);

if (!$dati_coefficiente) {
    $coefficiente_abbattimento = 1;
} else {
    $coefficiente_abbattimento = (float) $dati_coefficiente['valore'];
}

// 6. Determina il valore da usare (min, max, medio)
switch ($tipo_valore) {
    case 1:
        $valore = (float) $dati_omi['Compr_min'];
        break;
    case 3:
        $valore = (float) $dati_omi['Compr_max'];
        break;
    case 2:
    default:
        $valore = round(((float) $dati_omi['Compr_min'] + (float) $dati_omi['Compr_max']) / 2, 0);
        break;
}

// 7. Eventuali riduzioni e abbattimenti ulteriori configurati
$abbattimento = (float) Settings::get('ABBATTIMENTO_VALORE', 1);
$riduzione    = (float) Settings::get('PERCENTUALE_VALORE_VENALE', 1);

$valore_finale = round($valore * 0.2 * $coefficiente_destinazione * $coefficiente_abbattimento, 2);
$valore_riscatto = round($valore * 0.2 * $coefficiente_destinazione * $coefficiente_abbattimento * $abbattimento * $riduzione, 2);

echo json_encode([
    "PERIODO"         => $dati_omi["Periodo"],
    "VALORE"          => $valore_finale,
    "VALORE_RISCATTO" => $valore_riscatto
]);
