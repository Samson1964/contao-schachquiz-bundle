<?php

declare(strict_types=1);

/*
 * Contao Schachquiz Bundle.
 *
 * @license LGPL-3.0-or-later
 */

namespace Schachbulle\ContaoSchachquizBundle\Tests\Wertung;

use PHPUnit\Framework\TestCase;
use Schachbulle\ContaoSchachquizBundle\Wertung\Glicko2;
use Schachbulle\ContaoSchachquizBundle\Wertung\Schwierigkeit;
use Schachbulle\ContaoSchachquizBundle\Wertung\Wertungsstand;

/**
 * Prüft die Glicko-2-Rechnung gegen Glickmans veröffentlichtes Beispiel und
 * das Verhalten, auf das sich das Quiz verlässt.
 */
class Glicko2Test extends TestCase
{
    /**
     * Rechnet das Beispiel aus „Example of the Glicko-2 system“ (Glickman 2012)
     * nach: Spieler 1500/200/0,06 gegen 1400/30 (Sieg), 1550/100 (Niederlage)
     * und 1700/300 (Niederlage), τ = 0,5. Erwartet: 1464,06 / 151,52 / 0,05999.
     */
    public function testGlickmansBeispiel(): void
    {
        $glicko = new Glicko2(0.5, 0.0, 1000.0);
        $neu = $glicko->aktualisiere(new Wertungsstand(1500, 200, 0.06), [
            [new Wertungsstand(1400, 30), 1.0],
            [new Wertungsstand(1550, 100), 0.0],
            [new Wertungsstand(1700, 300), 0.0],
        ]);

        $this->assertEqualsWithDelta(1464.06, $neu->wertung, 0.01);
        $this->assertEqualsWithDelta(151.52, $neu->abweichung, 0.01);
        $this->assertEqualsWithDelta(0.05999, $neu->volatilitaet, 0.00001);
    }

    /**
     * Ohne Partien wächst nur die Abweichung; die Wertung bleibt.
     */
    public function testOhnePartienWaechstNurDieAbweichung(): void
    {
        $neu = (new Glicko2(0.5, 0.0, 1000.0))->aktualisiere(new Wertungsstand(1500, 200, 0.06), []);

        $this->assertSame(1500.0, $neu->wertung);
        $this->assertEqualsWithDelta(200.2714, $neu->abweichung, 0.001);
    }

    /**
     * Ein Sieg gegen eine schwere Frage bringt mehr als gegen eine leichte;
     * eine Niederlage gegen eine leichte kostet mehr als gegen eine schwere.
     */
    public function testSchwierigkeitBestimmtGewinnUndVerlust(): void
    {
        $glicko = new Glicko2();
        $spieler = new Wertungsstand(1500, 150);
        $leicht = Schwierigkeit::anfangsstand(2);
        $schwer = Schwierigkeit::anfangsstand(9);

        $gewinnLeicht = $glicko->partie($spieler, $leicht, 1.0)[0]->wertung - 1500;
        $gewinnSchwer = $glicko->partie($spieler, $schwer, 1.0)[0]->wertung - 1500;
        $verlustLeicht = 1500 - $glicko->partie($spieler, $leicht, 0.0)[0]->wertung;
        $verlustSchwer = 1500 - $glicko->partie($spieler, $schwer, 0.0)[0]->wertung;

        $this->assertGreaterThan($gewinnLeicht, $gewinnSchwer);
        $this->assertGreaterThan($verlustSchwer, $verlustLeicht);
        $this->assertGreaterThan(0, $gewinnLeicht);
        $this->assertGreaterThan(0, $verlustSchwer);
    }

    /**
     * Die Frage bewegt sich spiegelbildlich: Wer sie löst, drückt ihre Wertung.
     */
    public function testFrageVerliertWennSpielerGewinnt(): void
    {
        [$spieler, $frage] = (new Glicko2())->partie(new Wertungsstand(), Schwierigkeit::anfangsstand(5), 1.0);

        $this->assertGreaterThan(1500, $spieler->wertung);
        $this->assertLessThan(1500, $frage->wertung);
    }

    /**
     * Die Abweichung sinkt mit den Antworten und pendelt sich ein: Weil jede
     * Periode die Volatilität aufschlägt, bleibt sie bei rund 60 stehen —
     * eine Wertung bleibt also auch nach vielen Antworten beweglich.
     */
    public function testAbweichungPendeltSichEin(): void
    {
        $glicko = new Glicko2();
        $stand = new Wertungsstand();
        $frage = new Wertungsstand(1500, 45);

        for ($i = 0; $i < 300; ++$i) {
            $stand = $glicko->partie($stand, $frage, $i % 2)[0];
        }

        $this->assertGreaterThanOrEqual(45.0, $stand->abweichung);
        $this->assertLessThan(70.0, $stand->abweichung);
        $this->assertFalse($stand->istVorlaeufig());
        $this->assertTrue((new Wertungsstand())->istVorlaeufig());
    }

    /**
     * Die Untergrenze greift, wenn die Rechnung darunter fiele.
     */
    public function testAbweichungHatUntergrenze(): void
    {
        $neu = (new Glicko2(0.5, 80.0))->partie(new Wertungsstand(1500, 60), new Wertungsstand(1500, 60), 1.0)[0];

        $this->assertSame(80.0, $neu->abweichung);
    }

    /**
     * Gewinnerwartung bei gleicher Wertung ist 50 Prozent.
     */
    public function testGewinnerwartung(): void
    {
        $glicko = new Glicko2();

        $this->assertEqualsWithDelta(0.5, $glicko->gewinnerwartung(new Wertungsstand(), new Wertungsstand()), 1e-9);
        $this->assertGreaterThan(0.5, $glicko->gewinnerwartung(new Wertungsstand(1800), new Wertungsstand(1500)));
    }
}
