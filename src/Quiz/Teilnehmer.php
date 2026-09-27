<?php

declare(strict_types=1);

/*
 * Contao Schachquiz Bundle.
 *
 * @license LGPL-3.0-or-later
 */

namespace Schachbulle\ContaoSchachquizBundle\Quiz;

use Schachbulle\ContaoSchachquizBundle\Wertung\Wertungsstand;

/**
 * Der Spieler, der gerade Fragen beantwortet: ein Mitglied oder ein Gast.
 *
 * Mitglieder werden in tl_schachquiz_spieler geführt und erscheinen in der
 * Rangliste, Gäste nur in der Sitzung. Für die Rechnung macht das keinen
 * Unterschied, deshalb teilen sich beide diese Klasse.
 */
final class Teilnehmer
{
    /**
     * Legt einen Teilnehmer an.
     *
     * @param int           $mitglied   ID aus tl_member, 0 für einen Gast
     * @param Wertungsstand $stand      Aktuelle Wertung
     * @param int           $anzahl     Bisher beantwortete Fragen
     * @param int           $richtig    Davon richtig beantwortet
     * @param int           $serie      Richtige Antworten in Folge bis jetzt
     * @param int           $besteSerie Längste Folge richtiger Antworten
     */
    public function __construct(
        public readonly int $mitglied,
        public Wertungsstand $stand = new Wertungsstand(),
        public int $anzahl = 0,
        public int $richtig = 0,
        public int $serie = 0,
        public int $besteSerie = 0,
    ) {
    }

    /**
     * Sagt, ob es sich um einen Gast handelt.
     *
     * @return bool true ohne Mitgliedskonto
     */
    public function istGast(): bool
    {
        return 0 === $this->mitglied;
    }

    /**
     * Trägt das Ergebnis einer Antwort ein.
     *
     * @param Wertungsstand $neu     Der Wertungsstand nach der Antwort
     * @param bool          $richtig Ob die Antwort richtig war
     */
    public function verbuche(Wertungsstand $neu, bool $richtig): void
    {
        $this->stand = $neu;
        ++$this->anzahl;

        if ($richtig) {
            ++$this->richtig;
            ++$this->serie;
            $this->besteSerie = max($this->besteSerie, $this->serie);
        } else {
            $this->serie = 0;
        }
    }

    /**
     * Gibt die Angaben zurück, die das Frontend-Skript anzeigt.
     *
     * @return array<string, mixed> Wertung gerundet, Vorläufigkeit, Zähler
     *                              und ob es sich um einen Gast handelt
     */
    public function alsAnzeige(): array
    {
        return [
            'wertung' => (int) round($this->stand->wertung),
            'vorlaeufig' => $this->stand->istVorlaeufig(),
            'anzahl' => $this->anzahl,
            'richtig' => $this->richtig,
            'serie' => $this->serie,
            'besteSerie' => $this->besteSerie,
            'gast' => $this->istGast(),
        ];
    }
}
