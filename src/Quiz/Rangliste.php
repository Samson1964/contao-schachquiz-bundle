<?php

declare(strict_types=1);

/*
 * Contao Schachquiz Bundle.
 *
 * @license LGPL-3.0-or-later
 */

namespace Schachbulle\ContaoSchachquizBundle\Quiz;

use Doctrine\DBAL\Connection;
use Schachbulle\ContaoSchachquizBundle\Wertung\Wertungsstand;

/**
 * Liest die Ranglisten der Mitglieder.
 *
 * Drei Arten teilen sich dieselbe Logik für Plätze und den eigenen Platz:
 *
 * - AKTUELL: die heutige Wertung aller Mitglieder, deren Konto nicht
 *   deaktiviert ist.
 * - EWIG: die höchste je erreichte gefestigte Wertung jedes Mitglieds, mit
 *   dem Tag, an dem sie erreicht wurde.
 * - MONAT: der am Monatsersten gesicherte Stand (tl_schachquiz_rangliste).
 *   Namen stammen aus der Sicherung, damit alte Stände lesbar bleiben.
 *
 * In jede Liste kommen nur Mitglieder mit der im Modul eingestellten
 * Mindestzahl an beantworteten Fragen. Vorläufige Wertungen erscheinen mit,
 * das Template kennzeichnet sie mit einem Fragezeichen.
 */
class Rangliste
{
    public const AKTUELL = 'aktuell';
    public const EWIG = 'ewig';
    public const MONAT = 'monat';

    /**
     * Legt die Rangliste an.
     *
     * @param Connection $db Datenbankverbindung von Contao
     */
    public function __construct(private readonly Connection $db)
    {
    }

    /**
     * Gibt die ersten Plätze zurück.
     *
     * @param int    $anzahl     Wie viele Plätze gezeigt werden, mindestens 1
     * @param int    $mindestens Nötige Zahl beantworteter Fragen
     * @param string $format     Namensformat: „voll", „kurz" oder „benutzer"
     * @param string $art        AKTUELL, EWIG oder MONAT
     * @param string $monat      Bei MONAT der Stichtag als „JJJJ-MM", sonst leer
     *
     * @return list<array<string, mixed>> Plätze mit `platz`, `member`, `name`,
     *                                    `wertung`, `vorlaeufig`, `anzahl`,
     *                                    `quote` (in Prozent), `serie` und
     *                                    `datum` (bei EWIG der Tag der Höchstwertung)
     */
    public function plaetze(int $anzahl, int $mindestens, string $format, string $art = self::AKTUELL, string $monat = ''): array
    {
        [$von, $parameter, $wert] = $this->quelle($art, $monat);

        $zeilen = $this->db->fetchAllAssociative(
            'SELECT '.$this->spalten($art).' '.$von.' AND s.anzahl >= ?
                ORDER BY '.$wert.' DESC, s.anzahl DESC
                LIMIT '.max(1, $anzahl),
            [...$parameter, $mindestens]
        );

        $plaetze = [];

        foreach ($zeilen as $index => $zeile) {
            $plaetze[] = $this->platz($zeile, $index + 1, $format, $art);
        }

        return $plaetze;
    }

    /**
     * Ermittelt den Platz eines einzelnen Mitglieds.
     *
     * Das angemeldete Mitglied soll sich immer wiederfinden, auch unterhalb der
     * gezeigten Plätze. Hat es die Mindestzahl an Antworten noch nicht
     * erreicht, gibt es keinen Rang: `platz` ist null und `fehlen` sagt, wie
     * viele Antworten noch fehlen. In der ewigen Bestenliste gilt dasselbe,
     * solange es noch keine gefestigte Wertung gibt (`ohneBestwert`).
     *
     * @param int    $mitglied   ID aus tl_member
     * @param int    $mindestens Nötige Zahl beantworteter Fragen
     * @param string $format     Namensformat wie bei plaetze()
     * @param string $art        AKTUELL, EWIG oder MONAT
     * @param string $monat      Bei MONAT der Stichtag als „JJJJ-MM"
     *
     * @return array<string, mixed>|null Der Platz wie bei plaetze() mit `eigene`,
     *                                   `fehlen` und `ohneBestwert`; null, wenn das
     *                                   Mitglied nicht vorkommt (noch nie gespielt
     *                                   bzw. im gewählten Monat nicht gesichert)
     */
    public function eigenerPlatz(int $mitglied, int $mindestens, string $format, string $art = self::AKTUELL, string $monat = ''): ?array
    {
        [$von, $parameter, $wert] = $this->quelle($art, $monat, false);

        $zeile = $this->db->fetchAssociative(
            'SELECT '.$this->spalten($art).' '.$von.' AND s.member = ?',
            [...$parameter, $mitglied]
        );

        if (false === $zeile) {
            return null;
        }

        $fehlen = max(0, $mindestens - (int) $zeile['anzahl']);
        $ohneBestwert = self::EWIG === $art && (float) $zeile['beste_wertung'] <= 0;
        $eigene = ['eigene' => true, 'fehlen' => $fehlen, 'ohneBestwert' => $ohneBestwert];

        // Die eigenen Schlüssel zuerst: Der Plus-Operator behält bei gleichen
        // Schlüsseln den linken Wert, und platz() setzt `eigene` auf false.
        if ($fehlen > 0 || $ohneBestwert) {
            return $eigene + $this->platz($zeile, null, $format, $art);
        }

        [$von, $parameter, $wert] = $this->quelle($art, $monat);

        $besser = (int) $this->db->fetchOne(
            'SELECT COUNT(*) '.$von.' AND s.anzahl >= ? AND '.$wert.' > ?',
            [...$parameter, $mindestens, self::EWIG === $art ? $zeile['beste_wertung'] : $zeile['wertung']]
        );

        return $eigene + $this->platz($zeile, $besser + 1, $format, $art);
    }

    /**
     * Liefert Tabelle, Bedingungen und Sortierspalte einer Ranglistenart.
     *
     * @param string $art        AKTUELL, EWIG oder MONAT; Unbekanntes gilt als AKTUELL
     * @param string $monat      Stichtag bei MONAT
     * @param bool   $nurGewertet Bei EWIG nur Mitglieder mit Höchstwertung; aus
     *                            für die Suche nach dem eigenen Platz
     *
     * @return array{0: string, 1: list<mixed>, 2: string} FROM-Teil samt WHERE
     *                                                    (endet mit einer Bedingung,
     *                                                    an die „AND …" anschließt),
     *                                                    Parameter dafür und die
     *                                                    Spalte der Wertung
     */
    private function quelle(string $art, string $monat, bool $nurGewertet = true): array
    {
        if (self::MONAT === $art) {
            return ['FROM tl_schachquiz_rangliste s WHERE s.monat = ?', [$monat], 's.wertung'];
        }

        $von = "FROM tl_schachquiz_spieler s INNER JOIN tl_member m ON m.id = s.member WHERE m.disable != '1'";

        if (self::EWIG === $art) {
            return [$von.($nurGewertet ? ' AND s.beste_wertung > 0' : ''), [], 's.beste_wertung'];
        }

        return [$von, [], 's.wertung'];
    }

    /**
     * Gibt die Spaltenliste für eine Ranglistenart zurück.
     *
     * @param string $art AKTUELL, EWIG oder MONAT
     *
     * @return string SELECT-Liste; die Sicherung enthält die Namen selbst
     */
    private function spalten(string $art): string
    {
        return self::MONAT === $art ? 's.*' : 's.*, m.firstname, m.lastname, m.username';
    }

    /**
     * Formt eine Datenbankzeile zu einem Listenplatz.
     *
     * @param array<string, mixed> $zeile  Zeile aus tl_schachquiz_spieler samt
     *                                     Namensfeldern aus tl_member, oder aus
     *                                     tl_schachquiz_rangliste
     * @param int|null             $platz  Der Rang, null wenn noch nicht gewertet
     * @param string               $format Namensformat
     * @param string               $art    AKTUELL, EWIG oder MONAT
     *
     * @return array<string, mixed> Der aufbereitete Platz; bei EWIG ist `wertung`
     *                              die Höchstwertung und nie vorläufig
     */
    private function platz(array $zeile, ?int $platz, string $format, string $art = self::AKTUELL): array
    {
        $stand = Wertungsstand::ausZeile($zeile);
        $anzahl = (int) $zeile['anzahl'];
        $ewig = self::EWIG === $art;

        return [
            'platz' => $platz,
            'member' => (int) $zeile['member'],
            'name' => self::name($zeile, $format),
            'wertung' => (int) round($ewig ? (float) $zeile['beste_wertung'] : $stand->wertung),
            'vorlaeufig' => !$ewig && $stand->istVorlaeufig(),
            'anzahl' => $anzahl,
            'quote' => $anzahl > 0 ? (int) round(100 * (int) $zeile['richtig'] / $anzahl) : 0,
            'serie' => (int) $zeile['beste_serie'],
            'datum' => $ewig ? (int) ($zeile['beste_datum'] ?? 0) : 0,
            'eigene' => false,
        ];
    }

    /**
     * Setzt den angezeigten Namen zusammen.
     *
     * Die Vorgabe „kurz" (Vorname und Anfangsbuchstabe des Nachnamens) ist
     * ein Kompromiss zwischen Wiedererkennbarkeit im Verein und Datenschutz
     * auf einer öffentlichen Seite.
     *
     * @param array<string, mixed> $zeile  Enthält firstname, lastname, username
     * @param string               $format „voll", „kurz" oder „benutzer"
     *
     * @return string Der Name; fällt auf den Benutzernamen zurück, wenn Vor-
     *                und Nachname leer sind
     */
    public static function name(array $zeile, string $format): string
    {
        $vorname = trim((string) ($zeile['firstname'] ?? ''));
        $nachname = trim((string) ($zeile['lastname'] ?? ''));
        $benutzer = trim((string) ($zeile['username'] ?? ''));

        if ('benutzer' === $format || ('' === $vorname && '' === $nachname)) {
            return '' !== $benutzer ? $benutzer : '–';
        }

        if ('voll' === $format) {
            return trim($vorname.' '.$nachname);
        }

        return trim($vorname.('' !== $nachname ? ' '.mb_substr($nachname, 0, 1).'.' : ''));
    }
}
