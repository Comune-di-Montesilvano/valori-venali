<?php
/**
 * Parsing del CSV OMI (formato portale Sister) — funzioni pure, senza DB
 * (testate in tests/OmiCsvTest.php, anche con input casuali).
 */

/**
 * Periodo dalla prima riga del CSV, es. "Quotazioni - 2025 - 2 - Elaborazione del ..."
 * → "2025-2". Senza trattini o senza anno → "Sconosciuto" (mai vuoto).
 */
function periodoDaIntestazione(string $riga): string
{
    $parti = explode('-', $riga);
    $anno  = trim($parti[1] ?? '');
    if ($anno === '') {
        return 'Sconosciuto';
    }
    $semestre = trim($parti[2] ?? '');
    if ($semestre !== '' && !str_starts_with(strtolower($semestre), 'elaborazione')) {
        return $anno . '-' . $semestre;
    }
    return $anno;
}

/**
 * Una riga di dati del CSV → colonne di valori_omi e relativi valori, nello
 * stesso ordine. Solo le colonne presenti in $campiDB (whitelist dallo schema:
 * i nomi finiscono nell'SQL) — le altre sono ignorate. Celle mancanti → null.
 *
 * @param list<string|null> $struttura intestazione CSV (seconda riga)
 * @param list<string|null> $data      riga di dati
 * @param list<string>      $campiDB   colonne ammesse
 * @return array{0: list<string>, 1: list<string|null>} [colonne, valori]
 */
function mappaRigaOmi(array $struttura, array $data, array $campiDB): array
{
    $colonne = [];
    $valori  = [];
    foreach ($struttura as $idx => $campo) {
        $campo = trim((string) $campo);
        if (in_array($campo, $campiDB, true)) {
            $colonne[] = $campo;
            $valori[]  = isset($data[$idx]) ? trim((string) $data[$idx]) : null;
        }
    }
    return [$colonne, $valori];
}
