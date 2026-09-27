<?php

declare(strict_types=1);

/*
 * Contao Schachquiz Bundle.
 *
 * @license LGPL-3.0-or-later
 */

namespace Schachbulle\ContaoSchachquizBundle\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Prüft, dass deutsche und englische Sprachdateien dieselben Schlüssel führen
 * und die Platzhalter übereinstimmen.
 */
class SprachdateienTest extends TestCase
{
    private const VERZEICHNIS = __DIR__.'/../src/Resources/contao/languages/';

    /**
     * @return iterable<string, array{string}>
     */
    public static function dateien(): iterable
    {
        foreach (glob(self::VERZEICHNIS.'de/*.php') ?: [] as $pfad) {
            yield basename($pfad) => [basename($pfad)];
        }
    }

    /**
     * Beide Sprachen haben dieselben Schlüssel, und jeder Text hat in beiden
     * dieselben sprintf-Platzhalter in derselben Reihenfolge.
     *
     * @dataProvider dateien
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('dateien')]
    public function testDeutschUndEnglischPassenZusammen(string $datei): void
    {
        $this->assertFileExists(self::VERZEICHNIS.'en/'.$datei);

        $de = $this->flach($this->lade('de', $datei));
        $en = $this->flach($this->lade('en', $datei));

        $this->assertSame([], array_keys(array_diff_key($de, $en)), 'Fehlt im Englischen');
        $this->assertSame([], array_keys(array_diff_key($en, $de)), 'Fehlt im Deutschen');

        foreach ($de as $schluessel => $text) {
            preg_match_all('/%[ds]/', $text, $a);
            preg_match_all('/%[ds]/', $en[$schluessel], $b);
            $this->assertSame($a[0], $b[0], 'Platzhalter in '.$schluessel);
        }
    }

    /**
     * Lädt eine Sprachdatei in ein frisches TL_LANG.
     *
     * @return array<string, mixed>
     */
    private function lade(string $sprache, string $datei): array
    {
        $GLOBALS['TL_LANG'] = [];
        include self::VERZEICHNIS.$sprache.'/'.$datei;
        $ergebnis = $GLOBALS['TL_LANG'];
        unset($GLOBALS['TL_LANG']);

        return $ergebnis;
    }

    /**
     * Macht aus dem verschachtelten Array eine Liste „a.b.c => Text“.
     *
     * @param array<mixed> $werte
     *
     * @return array<string, string>
     */
    private function flach(array $werte, string $praefix = ''): array
    {
        $flach = [];

        foreach ($werte as $schluessel => $wert) {
            if (\is_array($wert)) {
                $flach += $this->flach($wert, $praefix.$schluessel.'.');
            } else {
                $flach[$praefix.$schluessel] = (string) $wert;
            }
        }

        return $flach;
    }
}
