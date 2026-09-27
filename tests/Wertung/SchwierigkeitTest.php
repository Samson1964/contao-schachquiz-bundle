<?php

declare(strict_types=1);

/*
 * Contao Schachquiz Bundle.
 *
 * @license LGPL-3.0-or-later
 */

namespace Schachbulle\ContaoSchachquizBundle\Tests\Wertung;

use PHPUnit\Framework\TestCase;
use Schachbulle\ContaoSchachquizBundle\Wertung\Schwierigkeit;

/**
 * Prüft die Übersetzung zwischen Stufe und Wertung.
 */
class SchwierigkeitTest extends TestCase
{
    /**
     * Stufe 1 = 900, Stufe 5 = 1500 (Startwertung), Stufe 10 = 2250;
     * Werte außerhalb werden auf die Grenzen gezogen.
     */
    public function testWertung(): void
    {
        $this->assertSame(900.0, Schwierigkeit::wertung(1));
        $this->assertSame(1500.0, Schwierigkeit::wertung(5));
        $this->assertSame(2250.0, Schwierigkeit::wertung(10));
        $this->assertSame(900.0, Schwierigkeit::wertung(0));
        $this->assertSame(2250.0, Schwierigkeit::wertung(99));
    }

    /**
     * Die Rückrechnung trifft jede Stufe und begrenzt Ausreißer.
     */
    public function testStufe(): void
    {
        for ($stufe = 1; $stufe <= 10; ++$stufe) {
            $this->assertSame($stufe, Schwierigkeit::stufe(Schwierigkeit::wertung($stufe)));
        }

        $this->assertSame(1, Schwierigkeit::stufe(400));
        $this->assertSame(10, Schwierigkeit::stufe(3000));
        $this->assertSame(6, Schwierigkeit::stufe(1600));
    }
}
