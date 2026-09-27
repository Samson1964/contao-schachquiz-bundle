<?php

declare(strict_types=1);

/*
 * Contao Schachquiz Bundle.
 *
 * @license LGPL-3.0-or-later
 */

namespace Schachbulle\ContaoSchachquizBundle\Wertung;

/**
 * Unveränderlicher Wertungsstand nach Glicko-2 in der gewohnten Elo-Skala.
 *
 * Gespeichert wird immer in der Anzeigeskala (Wertung um 1500, Abweichung in
 * Wertungspunkten), weil diese Werte so auch in der Datenbank und in der
 * Rangliste stehen. Die Umrechnung in die interne Glicko-2-Skala erledigt
 * allein die Klasse Glicko2.
 */
final class Wertungsstand
{
    /** Anfangswertung eines neuen Spielers. */
    public const START_WERTUNG = 1500.0;

    /**
     * Anfangsabweichung eines neuen Spielers, zugleich die Obergrenze.
     *
     * Glickman schlägt 350 vor. Im Quiz ließ das die erste Antwort um bis
     * zu 424 Punkte ausschlagen (Fehler bei einer sehr leichten Frage), was
     * sich eher nach Strafe als nach Einstufung anfühlt. Mit 200 sind es
     * höchstens rund 160, bei einer gleich schweren Frage etwa ±78; nach
     * etwa zwanzig Antworten verhalten sich beide Werte gleich.
     */
    public const START_ABWEICHUNG = 200.0;

    /** Anfangsvolatilität nach der Empfehlung von Glickman. */
    public const START_VOLATILITAET = 0.06;

    /**
     * Ab dieser Abweichung gilt eine Wertung als vorläufig. Der Wert folgt
     * lichess, das Wertungen mit einer Abweichung über 110 mit einem
     * Fragezeichen kennzeichnet.
     */
    public const VORLAEUFIG_AB = 110.0;

    /**
     * Legt einen Wertungsstand an.
     *
     * @param float $wertung      Die Wertung in der Elo-Skala, üblicherweise
     *                            zwischen 600 und 2800
     * @param float $abweichung   Die Wertungsabweichung (RD) in Wertungspunkten;
     *                            je kleiner, desto sicherer die Wertung
     * @param float $volatilitaet Die Volatilität σ, ein Maß dafür, wie stark
     *                            die Leistung schwankt; typisch um 0,06
     */
    public function __construct(
        public readonly float $wertung = self::START_WERTUNG,
        public readonly float $abweichung = self::START_ABWEICHUNG,
        public readonly float $volatilitaet = self::START_VOLATILITAET,
    ) {
    }

    /**
     * Baut einen Wertungsstand aus einer Datenbankzeile oder einem
     * Sitzungseintrag.
     *
     * Fehlende oder nicht numerische Werte fallen auf die Startwerte zurück,
     * damit ein unvollständiger Datensatz nicht zum Abbruch führt.
     *
     * @param array<string, mixed> $zeile Erwartet die Schlüssel `wertung`,
     *                                    `rd` und `vol`
     *
     * @return self Der gelesene Stand
     */
    public static function ausZeile(array $zeile): self
    {
        $zahl = static fn ($wert, float $vorgabe): float => is_numeric($wert) ? (float) $wert : $vorgabe;

        return new self(
            $zahl($zeile['wertung'] ?? null, self::START_WERTUNG),
            $zahl($zeile['rd'] ?? null, self::START_ABWEICHUNG),
            $zahl($zeile['vol'] ?? null, self::START_VOLATILITAET),
        );
    }

    /**
     * Gibt den Stand als Feldliste für Datenbank oder Sitzung zurück.
     *
     * @return array{wertung: float, rd: float, vol: float} Die drei Werte
     *                                                      unter den Spaltennamen
     */
    public function alsZeile(): array
    {
        return [
            'wertung' => $this->wertung,
            'rd' => $this->abweichung,
            'vol' => $this->volatilitaet,
        ];
    }

    /**
     * Sagt, ob die Wertung noch als vorläufig gilt.
     *
     * @return bool true, solange die Abweichung über der Grenze VORLAEUFIG_AB liegt
     */
    public function istVorlaeufig(): bool
    {
        return $this->abweichung > self::VORLAEUFIG_AB;
    }
}
