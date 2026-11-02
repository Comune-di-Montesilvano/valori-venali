<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Parsing del CSV OMI (src/includes/omi_csv.php): input non fidato caricato
 * dall'admin, i nomi colonna finiscono nell'SQL.
 */
final class OmiCsvTest extends TestCase
{
    private const CAMPI_DB = ['Zona', 'Cod_Tip', 'Descr_Tipologia', 'Stato', 'Compr_min', 'Compr_max'];

    /** @return iterable<string, array{string, string}> */
    public static function intestazioni(): iterable
    {
        yield 'anno e semestre'        => ['Quotazioni - 2025 - 2 - Elaborazione del 01/02/2026', '2025-2'];
        yield 'senza semestre'         => ['Quotazioni - 2025 - Elaborazione del 01/02/2026', '2025'];
        yield 'elaborazione maiuscolo' => ['Quotazioni - 2024 - ELABORAZIONE', '2024'];
        yield 'con a capo'             => ["Quotazioni - 2025 - 1\r\n", '2025-1'];
        yield 'senza trattini'         => ['Quotazioni 2025', 'Sconosciuto'];
        yield 'file vuoto'             => ['', 'Sconosciuto'];
        yield 'anno vuoto'             => ['Quotazioni - ', 'Sconosciuto'];
        yield 'trattino doppio'        => ['Quotazioni -- 2', 'Sconosciuto'];
        yield 'semestre vuoto'         => ['Quotazioni - 2025 - ', '2025'];
    }

    #[DataProvider('intestazioni')]
    public function testPeriodoDaIntestazione(string $riga, string $atteso): void
    {
        self::assertSame($atteso, periodoDaIntestazione($riga));
    }

    public function testMappaSoloColonneAmmesseNellOrdineDelCsv(): void
    {
        $struttura = ['Area_territoriale', ' Zona ', 'Cod_Tip', 'Colonna ignota', 'Compr_min'];
        $data      = ['Centro', 'B1', ' 20 ', 'x', '1000'];

        [$colonne, $valori] = mappaRigaOmi($struttura, $data, self::CAMPI_DB);

        self::assertSame(['Zona', 'Cod_Tip', 'Compr_min'], $colonne);
        self::assertSame(['B1', '20', '1000'], $valori);
    }

    public function testCelleMancantiDiventanoNull(): void
    {
        [$colonne, $valori] = mappaRigaOmi(['Zona', 'Cod_Tip', 'Stato'], ['B1'], self::CAMPI_DB);

        self::assertSame(['Zona', 'Cod_Tip', 'Stato'], $colonne);
        self::assertSame(['B1', null, null], $valori);
    }

    public function testNomeColonnaMaliziosoNonPassa(): void
    {
        $struttura = ['Zona`) VALUES (1); DROP TABLE users; --', 'Zona'];
        [$colonne] = mappaRigaOmi($struttura, ['x', 'B1'], self::CAMPI_DB);

        self::assertSame(['Zona'], $colonne);
    }

    /**
     * Fuzz con seme fisso: intestazioni e righe casuali (byte arbitrari,
     * separatori, null) non devono mai produrre colonne fuori whitelist,
     * disallineare colonne e valori o sollevare errori/deprecation.
     */
    public function testFuzzRigheCasuali(): void
    {
        mt_srand(20261102);
        $alfabeto = array_merge(self::CAMPI_DB, ['', ' ', '`', ';', '"', "\0", 'DROP', 'zona', 'Zona ']);

        for ($i = 0; $i < 2000; $i++) {
            $struttura = [];
            $data      = [];
            for ($c = mt_rand(0, 12); $c > 0; $c--) {
                $struttura[] = mt_rand(0, 9) === 0 ? null : $alfabeto[array_rand($alfabeto)] . (mt_rand(0, 4) === 0 ? random_bytes(2) : '');
            }
            for ($c = mt_rand(0, 12); $c > 0; $c--) {
                $data[] = mt_rand(0, 9) === 0 ? null : random_bytes(mt_rand(1, 8));
            }

            [$colonne, $valori] = mappaRigaOmi($struttura, $data, self::CAMPI_DB);

            self::assertCount(count($colonne), $valori);
            foreach ($colonne as $col) {
                self::assertContains($col, self::CAMPI_DB);
            }
            self::assertNotSame('', periodoDaIntestazione(implode('-', array_map('strval', $data))));
        }
    }
}
