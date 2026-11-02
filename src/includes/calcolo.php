<?php
/**
 * Formula del valore venale — funzioni pure, senza DB né richiesta HTTP
 * (testate in tests/CalcoloTest.php).
 *
 * Valore venale = Superficie × VMR × Coeff. Destinazione × Coeff. Abbattimento
 *                 [− oneri di adattamento]
 * VMR (logica Montesilvano) = 20% del valore OMI.
 */

/** Quota del valore OMI che costituisce il VMR */
const VMR_QUOTA = 0.2;
/** Detrazione per oneri di adattamento, sul valore unitario */
const SCONTO_ONERI_ADATTAMENTO = 0.05;

/** Tipo di valore OMI configurato per la destinazione (omi_destinazione_urbanistica.Valore) */
const VALORE_OMI_MIN   = 1;
const VALORE_OMI_MEDIO = 2;
const VALORE_OMI_MAX   = 3;

/**
 * Valore OMI di riferimento (€/mq) dalla riga valori_omi: minimo, massimo o
 * media tra i due (qualsiasi tipo diverso da min/max vale come medio).
 * null se il valore richiesto manca (min/max NULL nel DB): il chiamante lo
 * tratta come "nessun valore OMI trovato", mai come 0 €.
 *
 * @param array<string, mixed> $omiRow riga valori_omi (usa Compr_min, Compr_max)
 */
function valoreOmi(array $omiRow, int $tipoValore): ?float
{
    $min = $omiRow['Compr_min'];
    $max = $omiRow['Compr_max'];

    return match ($tipoValore) {
        VALORE_OMI_MIN => $min === null ? null : (float) $min,
        VALORE_OMI_MAX => $max === null ? null : (float) $max,
        default        => ((float) $min + (float) $max) / 2,
    };
}

/**
 * Superficie inserita dall'utente: accetta la virgola decimale italiana.
 * Valori non numerici → 0 (il chiamante li rifiuta come "superficie non valida").
 */
function parseSuperficie(string $input): float
{
    return (float) str_replace(',', '.', trim($input));
}

/**
 * Calcolo del valore venale.
 *
 * @return array{vmr: float, sconto_oneri: float, valore_unitario: float, valore_totale: float}
 */
function calcolaValoreVenale(
    float $valoreOmi,
    float $coeffDestinazione,
    float $coeffAbbattimento,
    float $superficie,
    bool $oneriAdattamento
): array {
    $vmr = $valoreOmi * VMR_QUOTA;
    $valoreUnitario = $vmr * $coeffDestinazione * $coeffAbbattimento;

    $scontoOneri = 0.0;
    if ($oneriAdattamento) {
        $scontoOneri = $valoreUnitario * SCONTO_ONERI_ADATTAMENTO;
        $valoreUnitario -= $scontoOneri;
    }

    return [
        'vmr'             => $vmr,
        'sconto_oneri'    => $scontoOneri,
        'valore_unitario' => $valoreUnitario,
        'valore_totale'   => $valoreUnitario * $superficie,
    ];
}
