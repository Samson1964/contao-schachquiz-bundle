<?php

declare(strict_types=1);

/*
 * Contao Schachquiz Bundle.
 *
 * @license LGPL-3.0-or-later
 */

namespace Schachbulle\ContaoSchachquizBundle\Wertung;

/**
 * Rechnet Wertungen nach dem Glicko-2-System von Mark Glickman.
 *
 * Grundlage ist die Beschreibung „Example of the Glicko-2 system" (Glickman,
 * 2012). Jede beantwortete Frage bildet eine eigene Wertungsperiode mit genau
 * einer Partie — so hält es auch lichess bei den Taktikaufgaben. Die Frage
 * ist dabei der Gegner: Wer eine Frage richtig beantwortet, „gewinnt" gegen
 * sie, und die Frage verliert entsprechend an Wertung.
 *
 * Die Klasse ist frei von Contao und Datenbank und lässt sich deshalb direkt
 * mit den Zahlen aus Glickmans Beispiel prüfen.
 */
final class Glicko2
{
    /** Umrechnungsfaktor zwischen Elo-Skala und Glicko-2-Skala (400 / ln 10). */
    private const SKALA = 173.7178;

    /** Abbruchgenauigkeit des Illinois-Verfahrens für die Volatilität. */
    private const EPSILON = 0.000001;

    /**
     * Legt den Rechner an.
     *
     * @param float $tau            Systemkonstante τ, begrenzt die Änderung der
     *                              Volatilität; Glickman empfiehlt 0,3 bis 1,2
     * @param float $minAbweichung  Untergrenze der Abweichung. Ohne sie würde
     *                              ein Vielspieler irgendwann fast unbeweglich;
     *                              45 entspricht lichess
     * @param float $maxAbweichung  Obergrenze der Abweichung, zugleich der
     *                              Startwert neuer Spieler
     */
    public function __construct(
        private readonly float $tau = 0.5,
        private readonly float $minAbweichung = 45.0,
        private readonly float $maxAbweichung = Wertungsstand::START_ABWEICHUNG,
    ) {
    }

    /**
     * Wertet eine einzelne Partie für beide Seiten aus.
     *
     * Beide neuen Stände werden aus den Werten **vor** der Partie berechnet;
     * die Reihenfolge der Aktualisierung spielt also keine Rolle.
     *
     * @param Wertungsstand $a         Die Wertung der ersten Seite (Spieler)
     * @param Wertungsstand $b         Die Wertung der zweiten Seite (Frage)
     * @param float         $ergebnisA 1 für einen Sieg der ersten Seite,
     *                                 0 für eine Niederlage, 0,5 für ein Remis
     *
     * @return array{0: Wertungsstand, 1: Wertungsstand} Die neuen Stände von
     *                                                   a und b
     */
    public function partie(Wertungsstand $a, Wertungsstand $b, float $ergebnisA): array
    {
        return [
            $this->aktualisiere($a, [[$b, $ergebnisA]]),
            $this->aktualisiere($b, [[$a, 1.0 - $ergebnisA]]),
        ];
    }

    /**
     * Berechnet den neuen Stand eines Spielers nach einer Wertungsperiode.
     *
     * Ohne Partien wächst nach Glicko-2 nur die Abweichung (Schritt 6 der
     * Beschreibung); die Wertung bleibt stehen.
     *
     * @param Wertungsstand                           $spieler  Der Stand vor der Periode
     * @param list<array{0: Wertungsstand, 1: float}> $partien Paare aus Gegnerstand
     *                                                          und eigenem Ergebnis
     *
     * @return Wertungsstand Der Stand nach der Periode
     */
    public function aktualisiere(Wertungsstand $spieler, array $partien): Wertungsstand
    {
        $mu = ($spieler->wertung - 1500.0) / self::SKALA;
        $phi = $spieler->abweichung / self::SKALA;
        $sigma = $spieler->volatilitaet;

        if ([] === $partien) {
            $phiNeu = sqrt($phi * $phi + $sigma * $sigma);

            return new Wertungsstand($spieler->wertung, $this->begrenze($phiNeu * self::SKALA), $sigma);
        }

        // Schritt 3 und 4: geschätzte Varianz v und Verbesserung Δ.
        $vKehrwert = 0.0;
        $summe = 0.0;

        foreach ($partien as [$gegner, $ergebnis]) {
            $muJ = ($gegner->wertung - 1500.0) / self::SKALA;
            $phiJ = $gegner->abweichung / self::SKALA;
            $g = $this->g($phiJ);
            $e = $this->erwartung($mu, $muJ, $g);

            $vKehrwert += $g * $g * $e * (1.0 - $e);
            $summe += $g * ($ergebnis - $e);
        }

        $v = 1.0 / $vKehrwert;
        $delta = $v * $summe;

        // Schritt 5: neue Volatilität.
        $sigmaNeu = $this->neueVolatilitaet($phi, $sigma, $v, $delta);

        // Schritt 6 und 7: neue Abweichung und neue Wertung.
        $phiStern = sqrt($phi * $phi + $sigmaNeu * $sigmaNeu);
        $phiNeu = 1.0 / sqrt(1.0 / ($phiStern * $phiStern) + 1.0 / $v);
        $muNeu = $mu + $phiNeu * $phiNeu * $summe;

        return new Wertungsstand(
            $muNeu * self::SKALA + 1500.0,
            $this->begrenze($phiNeu * self::SKALA),
            $sigmaNeu,
        );
    }

    /**
     * Gibt die Gewinnerwartung einer Seite gegen eine andere zurück.
     *
     * Wird im Frontend nicht gebraucht, hilft aber beim Einschätzen, wie
     * „schwer" eine Frage für einen Spieler ist.
     *
     * @param Wertungsstand $a Die Seite, deren Erwartung gesucht ist
     * @param Wertungsstand $b Die Gegenseite
     *
     * @return float Die Erwartung zwischen 0 und 1
     */
    public function gewinnerwartung(Wertungsstand $a, Wertungsstand $b): float
    {
        $muA = ($a->wertung - 1500.0) / self::SKALA;
        $muB = ($b->wertung - 1500.0) / self::SKALA;

        return $this->erwartung($muA, $muB, $this->g($b->abweichung / self::SKALA));
    }

    /**
     * Gewichtungsfunktion g(φ): dämpft den Einfluss unsicherer Gegner.
     *
     * @param float $phi Abweichung des Gegners in der Glicko-2-Skala
     *
     * @return float Ein Faktor zwischen 0 und 1
     */
    private function g(float $phi): float
    {
        return 1.0 / sqrt(1.0 + 3.0 * $phi * $phi / (M_PI * M_PI));
    }

    /**
     * Erwartungsfunktion E(μ, μj, φj).
     *
     * @param float $mu  Eigene Wertung in der Glicko-2-Skala
     * @param float $muJ Wertung des Gegners in der Glicko-2-Skala
     * @param float $g   Bereits berechnetes g(φj) des Gegners
     *
     * @return float Die erwartete Punktzahl zwischen 0 und 1
     */
    private function erwartung(float $mu, float $muJ, float $g): float
    {
        return 1.0 / (1.0 + exp(-$g * ($mu - $muJ)));
    }

    /**
     * Bestimmt die neue Volatilität mit dem Illinois-Verfahren (Schritt 5).
     *
     * Das Verfahren sucht die Nullstelle der Funktion f(x) aus Glickmans
     * Beschreibung. Es ist die 2012 überarbeitete Fassung, die ohne die
     * instabile Newton-Iteration der ersten Veröffentlichung auskommt.
     *
     * @param float $phi   Abweichung vor der Periode (Glicko-2-Skala)
     * @param float $sigma Volatilität vor der Periode
     * @param float $v     Geschätzte Varianz aus Schritt 3
     * @param float $delta Geschätzte Verbesserung aus Schritt 4
     *
     * @return float Die neue Volatilität
     */
    private function neueVolatilitaet(float $phi, float $sigma, float $v, float $delta): float
    {
        $a = log($sigma * $sigma);
        $phi2 = $phi * $phi;
        $delta2 = $delta * $delta;
        $tau2 = $this->tau * $this->tau;

        $f = static function (float $x) use ($phi2, $delta2, $v, $a, $tau2): float {
            $ex = exp($x);
            $nenner = $phi2 + $v + $ex;

            return $ex * ($delta2 - $phi2 - $v - $ex) / (2.0 * $nenner * $nenner) - ($x - $a) / $tau2;
        };

        $gA = $a;

        if ($delta2 > $phi2 + $v) {
            $gB = log($delta2 - $phi2 - $v);
        } else {
            $k = 1;

            while ($f($a - $k * $this->tau) < 0.0) {
                ++$k;
            }

            $gB = $a - $k * $this->tau;
        }

        $fA = $f($gA);
        $fB = $f($gB);

        // Die Schleife endet nach Glickman in wenigen Schritten; die Grenze
        // schützt nur vor einer Endlosschleife bei entarteten Eingaben.
        for ($i = 0; abs($gB - $gA) > self::EPSILON && $i < 100; ++$i) {
            $gC = $gA + ($gA - $gB) * $fA / ($fB - $fA);
            $fC = $f($gC);

            if ($fC * $fB <= 0.0) {
                $gA = $gB;
                $fA = $fB;
            } else {
                $fA /= 2.0;
            }

            $gB = $gC;
            $fB = $fC;
        }

        return exp($gA / 2.0);
    }

    /**
     * Hält eine Abweichung in den Grenzen aus dem Konstruktor.
     *
     * @param float $abweichung Die Abweichung in Wertungspunkten
     *
     * @return float Die begrenzte Abweichung
     */
    private function begrenze(float $abweichung): float
    {
        return max($this->minAbweichung, min($this->maxAbweichung, $abweichung));
    }
}
