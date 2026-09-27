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
 * Sucht die nächste Frage für einen Teilnehmer.
 *
 * Zwei Regeln bestimmen die Auswahl:
 *
 * 1. **Keine Wiederholung.** Eine Frage, die der Teilnehmer schon einmal
 *    gestellt bekam, kommt nie wieder — ob beantwortet oder per Themenwechsel
 *    verlassen. Bei Mitgliedern steht das im Verlauf, bei Gästen in der
 *    Sitzung. Sind alle Fragen erschöpft, gibt es keine neue.
 *
 * 2. **Schwierigkeit folgt der Wertung.** Wie bei den Taktikaufgaben von
 *    lichess bekommt jeder die Fragen, deren Wertung seiner eigenen am
 *    nächsten liegt. Mit steigender Wertung werden die Fragen also schwerer,
 *    nach Fehlern wieder leichter. Aus den nächstgelegenen Kandidaten wird
 *    zufällig gezogen, damit zwei gleich starke Spieler nicht dieselbe
 *    Reihenfolge sehen.
 */
class Fragenauswahl
{
    /** Anzahl der wertungsnächsten Kandidaten, aus denen zufällig gewählt wird. */
    private const KANDIDATEN = 8;

    /**
     * Legt die Auswahl an.
     *
     * @param Connection $db Datenbankverbindung von Contao
     */
    public function __construct(private readonly Connection $db)
    {
    }

    /**
     * Wählt die nächste Frage.
     *
     * @param list<int>  $themen     Erlaubte Themen-IDs; leer für alle
     * @param Teilnehmer $teilnehmer Der Spieler, dessen Wertung den Ausschlag gibt
     * @param list<int>  $gesehen    Fragen, die der Teilnehmer schon hatte
     *
     * @return array<string, mixed>|null Die Datenbankzeile der Frage samt
     *                                   Thementitel unter `thema_titel`, oder
     *                                   null, wenn keine ungesehene Frage übrig ist
     */
    public function waehle(array $themen, Teilnehmer $teilnehmer, array $gesehen): ?array
    {
        $kandidaten = $this->kandidaten($themen, $teilnehmer->stand->wertung, $gesehen);

        return [] === $kandidaten ? null : $kandidaten[array_rand($kandidaten)];
    }

    /**
     * Zählt die veröffentlichten Fragen einer Auswahl, gesehen oder nicht.
     *
     * Unterscheidet „es gibt hier gar keine Fragen" von „du hast alle
     * schon gehabt".
     *
     * @param list<int> $themen Erlaubte Themen-IDs; leer für alle
     *
     * @return int Anzahl der Fragen
     */
    public function anzahl(array $themen): int
    {
        $sql = "SELECT COUNT(*) FROM tl_schachquiz_items i
            INNER JOIN tl_schachquiz t ON t.id = i.pid
            WHERE i.published = '1' AND t.published = '1'";

        if ([] !== $themen) {
            $sql .= ' AND i.pid IN ('.implode(',', array_map('intval', $themen)).')';
        }

        return (int) $this->db->fetchOne($sql);
    }

    /**
     * Gibt die IDs der Fragen zurück, die ein Mitglied schon hatte.
     *
     * @param int $mitglied ID aus tl_member
     *
     * @return list<int> Die Frage-IDs, jede nur einmal
     */
    public function gesehenVonMitglied(int $mitglied): array
    {
        return array_map('intval', $this->db->fetchFirstColumn(
            'SELECT DISTINCT item FROM tl_schachquiz_verlauf WHERE member = ?',
            [$mitglied]
        ));
    }

    /**
     * Lädt eine einzelne veröffentlichte Frage samt Thementitel.
     *
     * @param int $id ID der Frage
     *
     * @return array<string, mixed>|null Die Zeile, oder null, wenn die Frage
     *                                   oder ihr Thema fehlt oder unveröffentlicht ist
     */
    public function lade(int $id): ?array
    {
        $zeile = $this->db->fetchAssociative(
            "SELECT i.*, t.title AS thema_titel FROM tl_schachquiz_items i
                INNER JOIN tl_schachquiz t ON t.id = i.pid
                WHERE i.id = ? AND i.published = '1' AND t.published = '1'",
            [$id]
        );

        return false === $zeile ? null : $zeile;
    }

    /**
     * Holt die wertungsnächsten Fragen außerhalb der gesehenen.
     *
     * Die ID-Listen werden als Ganzzahlen in die Abfrage geschrieben statt
     * als Parameterliste gebunden: Die Listenbindung unterscheidet sich
     * zwischen DBAL 3 (Contao 4.13) und DBAL 4 (Contao 5), die Umwandlung
     * nach int schließt Einschleusung trotzdem aus.
     *
     * @param list<int> $themen     Erlaubte Themen; leer für alle
     * @param float     $wertung    Wertung des Spielers
     * @param list<int> $ausschluss Auszuschließende Frage-IDs
     *
     * @return list<array<string, mixed>> Bis zu KANDIDATEN Zeilen
     */
    private function kandidaten(array $themen, float $wertung, array $ausschluss): array
    {
        $sql = "SELECT i.*, t.title AS thema_titel FROM tl_schachquiz_items i
            INNER JOIN tl_schachquiz t ON t.id = i.pid
            WHERE i.published = '1' AND t.published = '1'";

        if ([] !== $themen) {
            $sql .= ' AND i.pid IN ('.implode(',', array_map('intval', $themen)).')';
        }

        if ([] !== $ausschluss) {
            $sql .= ' AND i.id NOT IN ('.implode(',', array_map('intval', $ausschluss)).')';
        }

        $sql .= ' ORDER BY ABS(i.wertung - ?) LIMIT '.self::KANDIDATEN;

        return $this->db->fetchAllAssociative($sql, [$wertung]);
    }
}
