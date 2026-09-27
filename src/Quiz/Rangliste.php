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
 * Liest die Rangliste der Mitglieder.
 *
 * In die Liste kommen nur Mitglieder, deren Konto nicht deaktiviert ist und
 * die mindestens die im Modul eingestellte Zahl an Fragen beantwortet haben.
 * Vorläufige Wertungen (hohe Abweichung) erscheinen mit, werden aber im
 * Template mit einem Fragezeichen gekennzeichnet.
 */
class Rangliste
{
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
     *
     * @return list<array<string, mixed>> Plätze mit `platz`, `member`, `name`,
     *                                    `wertung`, `vorlaeufig`, `anzahl`,
     *                                    `quote` (in Prozent) und `serie`
     */
    public function plaetze(int $anzahl, int $mindestens, string $format): array
    {
        $zeilen = $this->db->fetchAllAssociative(
            "SELECT s.*, m.firstname, m.lastname, m.username FROM tl_schachquiz_spieler s
                INNER JOIN tl_member m ON m.id = s.member
                WHERE m.disable != '1' AND s.anzahl >= ?
                ORDER BY s.wertung DESC, s.anzahl DESC
                LIMIT ".max(1, $anzahl),
            [$mindestens]
        );

        $plaetze = [];

        foreach ($zeilen as $index => $zeile) {
            $plaetze[] = $this->platz($zeile, $index + 1, $format);
        }

        return $plaetze;
    }

    /**
     * Ermittelt den Platz eines einzelnen Mitglieds.
     *
     * Das angemeldete Mitglied soll sich immer wiederfinden, auch unterhalb der
     * gezeigten Plätze und auch, bevor es die Mindestzahl an Antworten erreicht
     * hat. Im zweiten Fall gibt es noch keinen Rang: `platz` ist dann null und
     * `fehlen` sagt, wie viele Antworten bis zur Wertung noch fehlen.
     *
     * @param int    $mitglied   ID aus tl_member
     * @param int    $mindestens Nötige Zahl beantworteter Fragen
     * @param string $format     Namensformat wie bei plaetze()
     *
     * @return array<string, mixed>|null Der Platz wie bei plaetze() mit `eigene` =
     *                                   true und `fehlen`; null nur, wenn das
     *                                   Mitglied noch gar nicht gespielt hat
     */
    public function eigenerPlatz(int $mitglied, int $mindestens, string $format): ?array
    {
        $zeile = $this->db->fetchAssociative(
            'SELECT s.*, m.firstname, m.lastname, m.username FROM tl_schachquiz_spieler s
                INNER JOIN tl_member m ON m.id = s.member
                WHERE s.member = ?',
            [$mitglied]
        );

        if (false === $zeile) {
            return null;
        }

        $fehlen = max(0, $mindestens - (int) $zeile['anzahl']);

        if ($fehlen > 0) {
            return ['eigene' => true, 'fehlen' => $fehlen] + $this->platz($zeile, null, $format);
        }

        $besser = (int) $this->db->fetchOne(
            "SELECT COUNT(*) FROM tl_schachquiz_spieler s
                INNER JOIN tl_member m ON m.id = s.member
                WHERE m.disable != '1' AND s.anzahl >= ? AND s.wertung > ?",
            [$mindestens, $zeile['wertung']]
        );

        // Die eigenen Schlüssel zuerst: Der Plus-Operator behält bei gleichen
        // Schlüsseln den linken Wert, und platz() setzt `eigene` auf false.
        return ['eigene' => true, 'fehlen' => 0] + $this->platz($zeile, $besser + 1, $format);
    }

    /**
     * Formt eine Datenbankzeile zu einem Listenplatz.
     *
     * @param array<string, mixed> $zeile  Zeile aus tl_schachquiz_spieler samt
     *                                     Namensfeldern aus tl_member
     * @param int|null             $platz  Der Rang, null wenn noch nicht gewertet
     * @param string               $format Namensformat
     *
     * @return array<string, mixed> Der aufbereitete Platz
     */
    private function platz(array $zeile, ?int $platz, string $format): array
    {
        $stand = Wertungsstand::ausZeile($zeile);
        $anzahl = (int) $zeile['anzahl'];

        return [
            'platz' => $platz,
            'member' => (int) $zeile['member'],
            'name' => self::name($zeile, $format),
            'wertung' => (int) round($stand->wertung),
            'vorlaeufig' => $stand->istVorlaeufig(),
            'anzahl' => $anzahl,
            'quote' => $anzahl > 0 ? (int) round(100 * (int) $zeile['richtig'] / $anzahl) : 0,
            'serie' => (int) $zeile['beste_serie'],
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
