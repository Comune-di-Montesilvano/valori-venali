<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Formula del valore venale (src/includes/calcolo.php). Valori attesi calcolati
 * a mano: se uno di questi test fallisce, cambia l'importo IMU accertato.
 */
final class CalcoloTest extends TestCase
{
    private const DELTA = 1e-9;

    /** @return iterable<string, array{int, ?float}> */
    public static function tipiValore(): iterable
    {
        yield 'minimo'              => [VALORE_OMI_MIN, 1000.0];
        yield 'medio'               => [VALORE_OMI_MEDIO, 1250.0];
        yield 'massimo'             => [VALORE_OMI_MAX, 1500.0];
        yield 'tipo ignoto = medio' => [99, 1250.0];
    }

    #[DataProvider('tipiValore')]
    public function testValoreOmiPerTipo(int $tipo, float $atteso): void
    {
        // PDO restituisce i DECIMAL come stringhe
        $riga = ['Compr_min' => '1000.00', 'Compr_max' => '1500.00'];
        self::assertEqualsWithDelta($atteso, valoreOmi($riga, $tipo), self::DELTA);
    }

    public function testValoreOmiMancanteNonDiventaZero(): void
    {
        self::assertNull(valoreOmi(['Compr_min' => null, 'Compr_max' => '1500'], VALORE_OMI_MIN));
        self::assertNull(valoreOmi(['Compr_min' => '1000', 'Compr_max' => null], VALORE_OMI_MAX));
    }

    public function testCalcoloSenzaOneri(): void
    {
        // VMR = 1250 × 20% = 250; unitario = 250 × 0,8 × 0,9 = 180; totale = 180 × 100
        $r = calcolaValoreVenale(1250.0, 0.8, 0.9, 100.0, false);

        self::assertEqualsWithDelta(250.0, $r['vmr'], self::DELTA);
        self::assertEqualsWithDelta(0.0, $r['sconto_oneri'], self::DELTA);
        self::assertEqualsWithDelta(180.0, $r['valore_unitario'], self::DELTA);
        self::assertEqualsWithDelta(18000.0, $r['valore_totale'], self::DELTA);
    }

    public function testCalcoloConOneriDiAdattamento(): void
    {
        // unitario 180 − 5% (9) = 171; totale = 171 × 100
        $r = calcolaValoreVenale(1250.0, 0.8, 0.9, 100.0, true);

        self::assertEqualsWithDelta(9.0, $r['sconto_oneri'], self::DELTA);
        self::assertEqualsWithDelta(171.0, $r['valore_unitario'], self::DELTA);
        self::assertEqualsWithDelta(17100.0, $r['valore_totale'], self::DELTA);
    }

    public function testCoefficientiNeutri(): void
    {
        // Coefficienti a 1 (abbattimento non selezionato): solo VMR × superficie
        $r = calcolaValoreVenale(800.0, 1.0, 1.0, 2.5, false);
        self::assertEqualsWithDelta(160.0 * 2.5, $r['valore_totale'], self::DELTA);
    }

    /**
     * Proprietà su input casuali (seme fisso: riproducibile): il totale è
     * sempre unitario × superficie, gli oneri tolgono esattamente il 5% e il
     * risultato non è mai negativo con input non negativi.
     */
    public function testProprietaSuInputCasuali(): void
    {
        mt_srand(20261102);
        for ($i = 0; $i < 1000; $i++) {
            $omi   = mt_rand(0, 1_000_000) / 100;
            $dest  = mt_rand(0, 200) / 100;
            $abb   = mt_rand(50, 100) / 100;
            $sup   = mt_rand(1, 10_000_000) / 100;

            $senza = calcolaValoreVenale($omi, $dest, $abb, $sup, false);
            $con   = calcolaValoreVenale($omi, $dest, $abb, $sup, true);

            self::assertEqualsWithDelta($senza['valore_unitario'] * $sup, $senza['valore_totale'], 1e-6);
            self::assertEqualsWithDelta($senza['valore_unitario'] * 0.95, $con['valore_unitario'], 1e-6);
            self::assertGreaterThanOrEqual(0.0, $con['valore_totale']);
            self::assertLessThanOrEqual($senza['valore_totale'] + 1e-6, $con['valore_totale']);
        }
    }

    /** @return iterable<string, array{string, float}> */
    public static function superfici(): iterable
    {
        yield 'intero'            => ['500', 500.0];
        yield 'punto decimale'    => ['1234.5', 1234.5];   // input type=number
        yield 'virgola decimale'  => ['1234,5', 1234.5];
        yield 'spazi'             => [' 42 ', 42.0];
        yield 'non numerico = 0'  => ['abc', 0.0];          // rifiutato come "superficie non valida"
        yield 'vuoto = 0'         => ['', 0.0];
    }

    #[DataProvider('superfici')]
    public function testParseSuperficie(string $input, float $atteso): void
    {
        self::assertEqualsWithDelta($atteso, parseSuperficie($input), self::DELTA);
    }

    public function testValoreRiscatto(): void
    {
        // unitario 180 × 0,9 × 0,5 = 81; moltiplicatori a 1 = invariato
        self::assertEqualsWithDelta(81.0, valoreRiscatto(180.0, 0.9, 0.5), self::DELTA);
        self::assertEqualsWithDelta(180.0, valoreRiscatto(180.0, 1.0, 1.0), self::DELTA);
    }
}
