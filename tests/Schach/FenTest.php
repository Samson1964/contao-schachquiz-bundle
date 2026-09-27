<?php

declare(strict_types=1);

/*
 * Contao Schachquiz Bundle.
 *
 * @license LGPL-3.0-or-later
 */

namespace Schachbulle\ContaoSchachquizBundle\Tests\Schach;

use PHPUnit\Framework\TestCase;
use Schachbulle\ContaoSchachquizBundle\Schach\Fen;

/**
 * Prüft die FEN-Prüfung.
 */
class FenTest extends TestCase
{
    /**
     * Die Grundstellung bleibt unverändert, eine verkürzte FEN wird ergänzt.
     */
    public function testGueltigeStellungen(): void
    {
        $grund = 'rnbqkbnr/pppppppp/8/8/8/8/PPPPPPPP/RNBQKBNR w KQkq - 0 1';

        $this->assertSame($grund, Fen::pruefe($grund));
        $this->assertSame('6k1/5ppp/8/8/8/8/5PPP/4R1K1 w - - 0 1', Fen::pruefe('  6k1/5ppp/8/8/8/8/5PPP/4R1K1  '));
        $this->assertSame('4k3/8/8/8/8/8/8/4K3 b - - 0 1', Fen::pruefe('4k3/8/8/8/8/8/8/4K3 b'));
    }

    /**
     * Das Zugrecht wird richtig erkannt.
     */
    public function testSchwarzAmZug(): void
    {
        $this->assertTrue(Fen::schwarzAmZug('4k3/8/8/8/8/8/8/4K3 b - - 0 1'));
        $this->assertFalse(Fen::schwarzAmZug('4k3/8/8/8/8/8/8/4K3 w - - 0 1'));
    }

    /**
     * Das Brett wird von der achten Reihe abwärts zerlegt.
     */
    public function testBrett(): void
    {
        $brett = Fen::brett('rnbqkbnr/pppppppp/8/8/8/8/PPPPPPPP/RNBQKBNR');

        $this->assertSame('r', $brett[0][0]);
        $this->assertSame('K', $brett[7][4]);
        $this->assertSame('', $brett[4][4]);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function ungueltig(): iterable
    {
        yield 'leer' => [''];
        yield 'sieben Reihen' => ['8/8/8/8/8/8/4K2k'];
        yield 'zu viele Felder' => ['4k4/8/8/8/8/8/8/4K3'];
        yield 'fremdes Zeichen' => ['4k3/8/8/8/8/8/8/4X3'];
        yield 'kein schwarzer König' => ['8/8/8/8/8/8/8/4K3'];
        yield 'zwei weiße Könige' => ['4k3/8/8/8/8/8/8/3KK3'];
        yield 'Bauer auf Grundreihe' => ['4k2P/8/8/8/8/8/8/4K3'];
        yield 'falsches Zugrecht' => ['4k3/8/8/8/8/8/8/4K3 x'];
        yield 'falsches En-passant-Feld' => ['4k3/8/8/8/8/8/8/4K3 w - e4'];
        yield 'falsche Rochade' => ['4k3/8/8/8/8/8/8/4K3 w KX'];
    }

    /**
     * Fehlerhafte Stellungen werden mit Meldung abgewiesen.
     *
     * @dataProvider ungueltig
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('ungueltig')]
    public function testUngueltigeStellungen(string $fen): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Fen::pruefe($fen);
    }
}
