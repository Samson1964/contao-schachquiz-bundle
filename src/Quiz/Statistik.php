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
 * Zählt gestellte Fragen und Antworten und wertet sie aus.
 *
 * Aufbau wie die Statistik des Schachaufgaben-Bundles: Gezählt wird
 * stundenweise in tl_schachquiz_statistik, getrennt nach Mitgliedern und
 * Gästen. Die Ranglisten (meistbeantwortete Fragen, aktivste Mitglieder,
 * Themen) kommen dagegen aus tl_schachquiz_verlauf, weil nur dort steht, wer
 * welche Frage beantwortet hat — also nur für Mitglieder.
 */
class Statistik
{
    public const GESTELLT = 'gestellt';
    public const RICHTIG = 'richtig';
    public const FALSCH = 'falsch';

    /** Alle gezählten Arten in der Reihenfolge der Anzeige. */
    public const ARTEN = [self::GESTELLT, self::RICHTIG, self::FALSCH];

    /**
     * Legt den Dienst an.
     *
     * @param Connection $db Datenbankverbindung von Contao
     */
    public function __construct(private readonly Connection $db)
    {
    }

    /**
     * Erhöht den Zähler einer Art in der laufenden Stunde um eins.
     *
     * Ein Fehler beim Zählen darf das Quiz nicht stören und wird deshalb
     * verschluckt: Die Statistik ist eine Beigabe, keine Voraussetzung. So
     * bleibt das Quiz auch lauffähig, wenn nach einem Update die Datenbank
     * noch nicht aktualisiert wurde.
     *
     * @param string   $art  GESTELLT, RICHTIG oder FALSCH
     * @param bool     $gast true für Gäste, false für Mitglieder
     * @param int|null $zeit Zeitpunkt (Unix-Zeit), für Prüfungen einstellbar; sonst jetzt
     */
    public function zaehle(string $art, bool $gast, ?int $zeit = null): void
    {
        $zeit ??= time();

        try {
            $this->db->executeStatement(
                'INSERT INTO tl_schachquiz_statistik (datum, stunde, art, gast, anzahl) VALUES (?, ?, ?, ?, 1)
                 ON DUPLICATE KEY UPDATE anzahl = anzahl + 1',
                [(int) date('Ymd', $zeit), (int) date('G', $zeit), $art, $gast ? '1' : '']
            );
        } catch (\Throwable) {
            // siehe Kommentarblock
        }
    }

    /**
     * Summiert die Zähler eines Zeitraums nach Art und Spielergruppe.
     *
     * @param int $von Erster Tag als JJJJMMTT
     * @param int $bis Letzter Tag als JJJJMMTT (einschließlich)
     *
     * @return array<string, array{mitglieder: int, gaeste: int, gesamt: int}> Je Art,
     *                                                                         zusätzlich
     *                                                                         „beantwortet"
     *                                                                         = richtig + falsch
     */
    public function summen(int $von, int $bis): array
    {
        $summen = [];

        foreach ([...self::ARTEN, 'beantwortet'] as $art) {
            $summen[$art] = ['mitglieder' => 0, 'gaeste' => 0, 'gesamt' => 0];
        }

        $zeilen = $this->db->fetchAllAssociative(
            'SELECT art, gast, SUM(anzahl) AS anzahl FROM tl_schachquiz_statistik WHERE datum BETWEEN ? AND ? GROUP BY art, gast',
            [$von, $bis]
        );

        foreach ($zeilen as $zeile) {
            if (!isset($summen[$zeile['art']])) {
                continue;
            }

            $gruppe = '1' === (string) $zeile['gast'] ? 'gaeste' : 'mitglieder';
            $anzahl = (int) $zeile['anzahl'];

            $summen[$zeile['art']][$gruppe] += $anzahl;
            $summen[$zeile['art']]['gesamt'] += $anzahl;

            if (self::GESTELLT !== $zeile['art']) {
                $summen['beantwortet'][$gruppe] += $anzahl;
                $summen['beantwortet']['gesamt'] += $anzahl;
            }
        }

        return $summen;
    }

    /**
     * Liefert den Verlauf einer oder mehrerer Arten, aufgeteilt nach Stunden,
     * Tagen oder Monaten.
     *
     * @param list<string> $arten   Die zu summierenden Arten, etwa [RICHTIG, FALSCH]
     *                              für „beantwortet"
     * @param int          $von     Erster Tag als JJJJMMTT
     * @param int          $bis     Letzter Tag als JJJJMMTT (einschließlich)
     * @param string       $einheit „stunde", „tag" oder „monat"
     *
     * @return array<int, int> Einheit (Stunde 0–23, Tag 1–31 bzw. Monat 1–12) => Summe
     */
    public function verlauf(array $arten, int $von, int $bis, string $einheit): array
    {
        $ausdruck = match ($einheit) {
            'stunde' => 'stunde',
            'tag' => 'datum % 100',
            default => 'FLOOR(datum / 100) % 100',
        };

        // Die Arten sind Konstanten dieser Klasse; alles andere fällt heraus.
        $arten = array_values(array_intersect($arten, self::ARTEN));

        if ([] === $arten) {
            return [];
        }

        $zeilen = $this->db->fetchAllAssociative(
            sprintf(
                "SELECT %s AS einheit, SUM(anzahl) AS anzahl FROM tl_schachquiz_statistik WHERE art IN ('%s') AND datum BETWEEN ? AND ? GROUP BY einheit",
                $ausdruck,
                implode("', '", $arten)
            ),
            [$von, $bis]
        );

        $verlauf = [];

        foreach ($zeilen as $zeile) {
            $verlauf[(int) $zeile['einheit']] = (int) $zeile['anzahl'];
        }

        return $verlauf;
    }

    /**
     * Die im Zeitraum am häufigsten von Mitgliedern beantworteten Fragen.
     *
     * @param int $beginn Unix-Zeit, einschließlich
     * @param int $ende   Unix-Zeit, ausschließlich
     * @param int $anzahl Höchstzahl der Zeilen
     *
     * @return list<array<string, mixed>> id, frage, thema, wertung, beantwortet, richtig
     */
    public function meistbeantwortet(int $beginn, int $ende, int $anzahl): array
    {
        return $this->db->fetchAllAssociative(
            sprintf(
                "SELECT i.id, i.frage, i.wertung, t.title AS thema, COUNT(*) AS beantwortet, SUM(v.richtig = '1') AS richtig
                 FROM tl_schachquiz_verlauf v
                 INNER JOIN tl_schachquiz_items i ON i.id = v.item
                 LEFT JOIN tl_schachquiz t ON t.id = i.pid
                 WHERE v.antwort != '' AND v.tstamp >= ? AND v.tstamp < ?
                 GROUP BY i.id, i.frage, i.wertung, t.title ORDER BY beantwortet DESC, i.id LIMIT %d",
                max(1, $anzahl)
            ),
            [$beginn, $ende]
        );
    }

    /**
     * Die im Zeitraum aktivsten Mitglieder.
     *
     * @param int $beginn Unix-Zeit, einschließlich
     * @param int $ende   Unix-Zeit, ausschließlich
     * @param int $anzahl Höchstzahl der Zeilen
     *
     * @return list<array{name: string, beantwortet: int, richtig: int, wertung: int}> Name
     *                                                                               im Kurzformat
     */
    public function aktivsteMitglieder(int $beginn, int $ende, int $anzahl): array
    {
        $zeilen = $this->db->fetchAllAssociative(
            sprintf(
                "SELECT m.firstname, m.lastname, m.username, COUNT(*) AS beantwortet, SUM(v.richtig = '1') AS richtig, MAX(s.wertung) AS wertung
                 FROM tl_schachquiz_verlauf v
                 INNER JOIN tl_member m ON m.id = v.member
                 LEFT JOIN tl_schachquiz_spieler s ON s.member = v.member
                 WHERE v.antwort != '' AND v.tstamp >= ? AND v.tstamp < ?
                 GROUP BY v.member, m.firstname, m.lastname, m.username ORDER BY beantwortet DESC, v.member LIMIT %d",
                max(1, $anzahl)
            ),
            [$beginn, $ende]
        );

        return array_map(
            static fn (array $zeile): array => [
                'name' => Rangliste::name($zeile, 'kurz'),
                'beantwortet' => (int) $zeile['beantwortet'],
                'richtig' => (int) $zeile['richtig'],
                'wertung' => (int) round((float) $zeile['wertung']),
            ],
            $zeilen
        );
    }

    /**
     * Antworten der Mitglieder je Thema im Zeitraum.
     *
     * @param int $beginn Unix-Zeit, einschließlich
     * @param int $ende   Unix-Zeit, ausschließlich
     *
     * @return list<array{thema: string, beantwortet: int, richtig: int}> Meistbeantwortete zuerst
     */
    public function themen(int $beginn, int $ende): array
    {
        return array_map(
            static fn (array $zeile): array => [
                'thema' => (string) $zeile['thema'],
                'beantwortet' => (int) $zeile['beantwortet'],
                'richtig' => (int) $zeile['richtig'],
            ],
            $this->db->fetchAllAssociative(
                "SELECT t.title AS thema, COUNT(*) AS beantwortet, SUM(v.richtig = '1') AS richtig
                 FROM tl_schachquiz_verlauf v
                 INNER JOIN tl_schachquiz_items i ON i.id = v.item
                 INNER JOIN tl_schachquiz t ON t.id = i.pid
                 WHERE v.antwort != '' AND v.tstamp >= ? AND v.tstamp < ?
                 GROUP BY t.id, t.title ORDER BY beantwortet DESC, t.title",
                [$beginn, $ende]
            )
        );
    }

    /**
     * Zahlen, die nicht vom Zeitraum abhängen, und neue Mitglieder im Zeitraum.
     *
     * @param int $beginn Unix-Zeit, einschließlich
     * @param int $ende   Unix-Zeit, ausschließlich
     *
     * @return array{fragen: int, spieler: int, neueSpieler: int} Veröffentlichte Fragen,
     *                                                            Mitglieder mit Wertung,
     *                                                            erste Nutzung im Zeitraum
     */
    public function bestand(int $beginn, int $ende): array
    {
        return [
            'fragen' => (int) $this->db->fetchOne(
                "SELECT COUNT(*) FROM tl_schachquiz_items i INNER JOIN tl_schachquiz t ON t.id = i.pid WHERE i.published = '1' AND t.published = '1'"
            ),
            'spieler' => (int) $this->db->fetchOne('SELECT COUNT(*) FROM tl_schachquiz_spieler WHERE anzahl > 0'),
            'neueSpieler' => (int) $this->db->fetchOne('SELECT COUNT(*) FROM tl_schachquiz_spieler WHERE erste_nutzung >= ? AND erste_nutzung < ?', [$beginn, $ende]),
        ];
    }
}
