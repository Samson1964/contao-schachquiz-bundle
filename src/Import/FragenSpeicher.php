<?php

declare(strict_types=1);

/*
 * Contao Schachquiz Bundle.
 *
 * @license LGPL-3.0-or-later
 */

namespace Schachbulle\ContaoSchachquizBundle\Import;

use Doctrine\DBAL\Connection;
use Schachbulle\ContaoSchachquizBundle\Wertung\Schwierigkeit;

/**
 * Schreibt eingelesene Fragen in die Datenbank.
 *
 * Zwei Betriebsarten:
 *
 * - Zielthema gewählt: Alle Fragen landen in diesem Thema, Themenangaben
 *   der Datei werden übergangen.
 * - Kein Zielthema: Die Themen der Datei werden übernommen. Gibt es schon
 *   ein Thema gleichen Titels, kommen die Fragen dort hinzu, sonst wird es
 *   angelegt. Fragen ohne Themenangabe landen in einem neuen Thema „Import"
 *   mit Datum.
 *
 * Eine Frage, deren Text im Zielthema bereits vorkommt, wird übersprungen.
 * Damit lässt sich dieselbe Datei gefahrlos zweimal einspielen, etwa nach
 * einer Korrektur einzelner Zeilen.
 */
class FragenSpeicher
{
    /**
     * Legt den Speicher an.
     *
     * @param Connection $db Datenbankverbindung von Contao
     */
    public function __construct(private readonly Connection $db)
    {
    }

    /**
     * Speichert alle Fragen eines Importergebnisses.
     *
     * Der ganze Import läuft in einer Transaktion: Scheitert eine Zeile an
     * der Datenbank, bleibt nichts halb eingespielt zurück.
     *
     * @param Importergebnis $ergebnis   Die geprüften Fragen
     * @param int            $zielThema  ID des Zielthemas, 0 für „Themen aus der Datei"
     *
     * @throws \Throwable Datenbankfehler werden nach dem Zurückrollen weitergereicht
     *
     * @return array{fragen: int, uebersprungen: int, neueThemen: int} Zähler
     *                                                                 für die Rückmeldung
     */
    public function speichere(Importergebnis $ergebnis, int $zielThema): array
    {
        $zaehler = ['fragen' => 0, 'uebersprungen' => 0, 'neueThemen' => 0];

        $this->db->beginTransaction();

        try {
            foreach ($ergebnis->themen as $thema) {
                if ([] === $thema['fragen']) {
                    continue;
                }

                $pid = $zielThema > 0 ? $zielThema : $this->themaFuer($thema['titel'], $thema['beschreibung'], $zaehler);

                foreach ($thema['fragen'] as $frage) {
                    if ($this->gibtEs($pid, $frage['frage'])) {
                        ++$zaehler['uebersprungen'];

                        continue;
                    }

                    $this->db->insert('tl_schachquiz_items', $this->zeile($pid, $frage));
                    ++$zaehler['fragen'];
                }
            }

            $this->db->commit();
        } catch (\Throwable $ausnahme) {
            $this->db->rollBack();

            throw $ausnahme;
        }

        return $zaehler;
    }

    /**
     * Baut die Datenbankzeile einer Frage.
     *
     * `richtig` wird wie vom Mehrfach-Kontrollkästchen des Backends als
     * serialisiertes Array von Zeichenketten gespeichert, damit die Maske
     * importierte Fragen genauso anzeigt wie von Hand angelegte.
     *
     * @param int                  $pid   ID des Themas
     * @param array<string, mixed> $frage Geprüfte Frage aus dem FragenLeser
     *
     * @return array<string, mixed> Spalten und Werte für tl_schachquiz_items
     */
    public function zeile(int $pid, array $frage): array
    {
        $stand = Schwierigkeit::anfangsstand((int) $frage['schwierigkeit']);

        $zeile = [
            'pid' => $pid,
            'tstamp' => time(),
            'frage' => $frage['frage'],
            'typ' => $frage['typ'],
            'fen' => $frage['fen'],
            'brett' => $frage['brett'] ?? 'auto',
            'richtig' => serialize(array_map('strval', $frage['richtig'])),
            'erklaerung' => $frage['erklaerung'],
            'schwierigkeit' => (int) $frage['schwierigkeit'],
            'wertung' => $stand->wertung,
            'rd' => $stand->abweichung,
            'vol' => $stand->volatilitaet,
            'anzahl' => 0,
            'anzahl_richtig' => 0,
            'published' => '1',
        ];

        for ($i = 1; $i <= FragenLeser::MAX_ANTWORTEN; ++$i) {
            $zeile['antwort'.$i] = $frage['antworten'][$i - 1] ?? '';
        }

        return $zeile;
    }

    /**
     * Findet oder erzeugt das Thema zu einem Titel aus der Datei.
     *
     * @param string                $titel        Titel aus der Datei, leer für „ohne Thema"
     * @param string                $beschreibung Beschreibung aus der Datei
     * @param array<string, int>    $zaehler      Zählt neu angelegte Themen mit
     *
     * @return int ID des gefundenen oder angelegten Themas
     */
    private function themaFuer(string $titel, string $beschreibung, array &$zaehler): int
    {
        if ('' === $titel) {
            $titel = 'Import '.date('Y-m-d H:i');
        }

        $id = $this->db->fetchOne('SELECT id FROM tl_schachquiz WHERE title = ?', [$titel]);

        if (false !== $id) {
            return (int) $id;
        }

        $this->db->insert('tl_schachquiz', [
            'tstamp' => time(),
            'title' => $titel,
            'beschreibung' => $beschreibung,
            'published' => '1',
        ]);

        ++$zaehler['neueThemen'];

        return (int) $this->db->lastInsertId();
    }

    /**
     * Prüft, ob eine Frage mit diesem Text im Thema schon existiert.
     *
     * @param int    $pid   ID des Themas
     * @param string $text  Der Fragetext
     *
     * @return bool true bei einem wortgleichen Treffer
     */
    private function gibtEs(int $pid, string $text): bool
    {
        return false !== $this->db->fetchOne('SELECT id FROM tl_schachquiz_items WHERE pid = ? AND frage = ?', [$pid, $text]);
    }
}
