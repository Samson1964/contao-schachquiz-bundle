<?php

declare(strict_types=1);

/*
 * Contao Schachquiz Bundle.
 *
 * @license LGPL-3.0-or-later
 */

namespace Schachbulle\ContaoSchachquizBundle\Wertung;

/**
 * Übersetzt den redaktionellen Schwierigkeitsgrad einer Frage in eine
 * Anfangswertung.
 *
 * Der Redakteur vergibt Stufen von 1 (sehr leicht) bis 10 (sehr schwer).
 * Daraus wird die Wertung, mit der die Frage ins Rennen geht: Stufe 1 steht
 * für 900, jede weitere Stufe für 150 Punkte mehr, Stufe 10 also für 2250.
 * Ein Spieler mit Startwertung 1500 hat damit gegen Stufe 5 (1500) eine
 * Erwartung von 50 Prozent.
 *
 * Nach Glicko-2 gewinnt man für eine richtig beantwortete schwere Frage
 * viele Punkte, für eine leichte wenige; umgekehrt kostet ein Fehler bei
 * einer leichten Frage viel und bei einer schweren wenig. Mit jeder Antwort
 * eines Mitglieds wandert die Wertung der Frage dorthin, wo sie tatsächlich
 * hingehört — die redaktionelle Stufe ist nur der Ausgangspunkt.
 */
final class Schwierigkeit
{
    public const MIN = 1;
    public const MAX = 10;

    private const BASIS = 900.0;
    private const SCHRITT = 150.0;

    /**
     * Anfangsabweichung einer neuen Frage. Sie liegt unter der eines neuen
     * Spielers, weil der Redakteur mit der Stufe schon eine begründete
     * Einschätzung abgibt.
     */
    public const START_ABWEICHUNG = 200.0;

    /**
     * Gibt die Anfangswertung zu einer Stufe zurück.
     *
     * @param int $stufe Schwierigkeitsgrad; Werte außerhalb 1 bis 10 werden
     *                   auf den nächsten gültigen Wert gezogen
     *
     * @return float Die Anfangswertung der Frage
     */
    public static function wertung(int $stufe): float
    {
        $stufe = max(self::MIN, min(self::MAX, $stufe));

        return self::BASIS + ($stufe - 1) * self::SCHRITT;
    }

    /**
     * Gibt den Anfangsstand einer neuen Frage zurück.
     *
     * @param int $stufe Schwierigkeitsgrad von 1 bis 10
     *
     * @return Wertungsstand Wertung nach Stufe, Abweichung START_ABWEICHUNG
     *                       und Standardvolatilität
     */
    public static function anfangsstand(int $stufe): Wertungsstand
    {
        return new Wertungsstand(self::wertung($stufe), self::START_ABWEICHUNG, Wertungsstand::START_VOLATILITAET);
    }

    /**
     * Schätzt die Stufe zu einer Wertung, etwa um im Frontend anzuzeigen,
     * wie schwer eine Frage inzwischen tatsächlich ist.
     *
     * @param float $wertung Die aktuelle Wertung der Frage
     *
     * @return int Die nächstgelegene Stufe von 1 bis 10
     */
    public static function stufe(float $wertung): int
    {
        $stufe = (int) round(($wertung - self::BASIS) / self::SCHRITT) + 1;

        return max(self::MIN, min(self::MAX, $stufe));
    }
}
