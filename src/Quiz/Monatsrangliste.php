<?php

declare(strict_types=1);

/*
 * Contao Schachquiz Bundle.
 *
 * @license LGPL-3.0-or-later
 */

namespace Schachbulle\ContaoSchachquizBundle\Quiz;

use Doctrine\DBAL\Connection;

/**
 * Sichert die Rangliste am Monatsersten.
 *
 * Gesichert wird der Stand aller Mitglieder, die mindestens eine Frage
 * beantwortet haben, unter dem Monat des Stichtags („JJJJ-MM“). Einen Rang
 * speichert die Sicherung nicht, weil er von der Mindestzahl an Antworten
 * abhängt, die jedes Ranglistenmodul selbst festlegt.
 *
 * Jeder Monat wird höchstens einmal gesichert. Das macht den Cronjob
 * unempfindlich dagegen, dass Contao ihn nach einer Pause nachholt oder
 * mehrere Server ihn gleichzeitig anstoßen.
 */
class Monatsrangliste
{
    /**
     * Bis zu diesem Tag des Monats darf eine verpasste Sicherung nachgeholt
     * werden. Danach würde der Stand zu weit vom Monatsersten abweichen.
     */
    public const NACHHOLEN_BIS_TAG = 7;

    /**
     * Legt den Dienst an.
     *
     * @param Connection $db Datenbankverbindung von Contao
     */
    public function __construct(private readonly Connection $db)
    {
    }

    /**
     * Sichert den Stand für den Monat des angegebenen Zeitpunkts.
     *
     * @param \DateTimeImmutable $jetzt     Zeitpunkt der Sicherung; sein Monat wird
     *                                      zum Stichtag
     * @param bool               $erzwingen Auch nach dem NACHHOLEN_BIS_TAG-ten sichern,
     *                                      etwa von Hand über die Konsole
     *
     * @return int|null Zahl der gesicherten Mitglieder; null, wenn nichts zu
     *                  tun war (Monat schon gesichert oder zu spät im Monat)
     */
    public function sichere(\DateTimeImmutable $jetzt, bool $erzwingen = false): ?int
    {
        $monat = $jetzt->format('Y-m');

        if (!$erzwingen && (int) $jetzt->format('j') > self::NACHHOLEN_BIS_TAG) {
            return null;
        }

        if ($this->istGesichert($monat)) {
            return null;
        }

        // Ein einziges INSERT … SELECT: Alle Zeilen eines Monats entstehen
        // zugleich, und der Eindeutigkeitsschlüssel (monat, member) verhindert
        // eine doppelte Sicherung, falls zwei Läufe gleichzeitig starten.
        return (int) $this->db->executeStatement(
            'INSERT IGNORE INTO tl_schachquiz_rangliste
                (tstamp, monat, member, firstname, lastname, username, wertung, rd, anzahl, richtig, beste_serie, beste_wertung)
             SELECT ?, ?, s.member, m.firstname, m.lastname, m.username, s.wertung, s.rd, s.anzahl, s.richtig, s.beste_serie, s.beste_wertung
                FROM tl_schachquiz_spieler s
                INNER JOIN tl_member m ON m.id = s.member
                WHERE s.anzahl > 0',
            [$jetzt->getTimestamp(), $monat]
        );
    }

    /**
     * Sagt, ob ein Monat schon gesichert ist.
     *
     * @param string $monat Monat als „JJJJ-MM“
     *
     * @return bool true, wenn es für den Monat mindestens eine Zeile gibt
     */
    public function istGesichert(string $monat): bool
    {
        return false !== $this->db->fetchOne('SELECT id FROM tl_schachquiz_rangliste WHERE monat = ? LIMIT 1', [$monat]);
    }

    /**
     * Gibt die gesicherten Monate zurück, der neueste zuerst.
     *
     * @return list<array{monat: string, zeit: int}> Monat als „JJJJ-MM“ und
     *                                                Zeitpunkt der Sicherung
     */
    public function monate(): array
    {
        return array_map(
            static fn (array $zeile): array => ['monat' => (string) $zeile['monat'], 'zeit' => (int) $zeile['zeit']],
            $this->db->fetchAllAssociative('SELECT monat, MIN(tstamp) AS zeit FROM tl_schachquiz_rangliste GROUP BY monat ORDER BY monat DESC')
        );
    }
}
