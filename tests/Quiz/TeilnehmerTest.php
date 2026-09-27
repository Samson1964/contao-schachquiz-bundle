<?php

declare(strict_types=1);

/*
 * Contao Schachquiz Bundle.
 *
 * @license LGPL-3.0-or-later
 */

namespace Schachbulle\ContaoSchachquizBundle\Tests\Quiz;

use PHPUnit\Framework\TestCase;
use Schachbulle\ContaoSchachquizBundle\Quiz\Teilnehmer;
use Schachbulle\ContaoSchachquizBundle\Wertung\Wertungsstand;

/**
 * Prüft die Buchführung eines Teilnehmers: erste Nutzung, Höchstwertung, Folgen.
 */
class TeilnehmerTest extends TestCase
{
    /**
     * Die erste Antwort setzt die erste Nutzung, spätere ändern sie nicht.
     */
    public function testErsteNutzung(): void
    {
        $t = new Teilnehmer(7);
        $t->verbuche(new Wertungsstand(1520, 190), true, 1000);
        $t->verbuche(new Wertungsstand(1540, 180), true, 2000);

        $this->assertSame(1000, $t->ersteNutzung);
    }

    /**
     * Vorläufige Wertungen zählen nicht als Höchstwert, gefestigte schon; ein
     * späterer, niedrigerer Stand ändert weder Wert noch Datum.
     */
    public function testHoechstwertNurGefestigt(): void
    {
        $t = new Teilnehmer(7);

        $t->verbuche(new Wertungsstand(1700, 150), true, 1000);
        $this->assertSame(0.0, $t->besteWertung, 'vorläufige 1700 zählt nicht');

        $t->verbuche(new Wertungsstand(1600, 100), true, 2000);
        $this->assertSame(1600.0, $t->besteWertung);
        $this->assertSame(2000, $t->besteDatum);

        $t->verbuche(new Wertungsstand(1580, 95), false, 3000);
        $this->assertSame(1600.0, $t->besteWertung);
        $this->assertSame(2000, $t->besteDatum);

        $t->verbuche(new Wertungsstand(1650, 90), true, 4000);
        $this->assertSame(1650.0, $t->besteWertung);
        $this->assertSame(4000, $t->besteDatum);
    }

    /**
     * Folge und längste Folge richtiger Antworten.
     */
    public function testFolgen(): void
    {
        $t = new Teilnehmer(0);

        foreach ([true, true, true, false, true] as $richtig) {
            $t->verbuche(new Wertungsstand(), $richtig, 1);
        }

        $this->assertSame(1, $t->serie);
        $this->assertSame(3, $t->besteSerie);
        $this->assertSame(4, $t->richtig);
        $this->assertSame(5, $t->anzahl);
    }
}
