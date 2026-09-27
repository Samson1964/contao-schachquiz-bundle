<?php

declare(strict_types=1);

/*
 * Contao Schachquiz Bundle.
 *
 * @license LGPL-3.0-or-later
 */

namespace Schachbulle\ContaoSchachquizBundle;

use Contao\System;

/**
 * Liest Texte des Bundles aus den Contao-Sprachdateien.
 *
 * Die Rückrufe der Data Container laufen zu Zeitpunkten, an denen die
 * Sprachdatei ihrer Tabelle nicht sicher geladen ist (der DcaLoader lädt
 * keine Sprachdateien). Die Klasse lädt sie deshalb vor jedem Zugriff; Contao
 * merkt sich geladene Dateien, der wiederholte Aufruf kostet also nichts.
 */
final class Sprache
{
    /**
     * Gibt einen Text zurück, auf Wunsch mit eingesetzten Werten.
     *
     * @param string           $datei      Name der Sprachdatei und des Schlüssels
     *                                     unter TL_LANG, etwa „tl_schachquiz_items“
     * @param string           $schluessel Schlüssel des Textes
     * @param int|float|string ...$werte   Werte für die Platzhalter (sprintf)
     *
     * @return string Der Text; fehlt er, der Schlüssel selbst, damit die Lücke
     *                auffällt statt eine leere Stelle zu hinterlassen
     */
    public static function text(string $datei, string $schluessel, int|float|string ...$werte): string
    {
        System::loadLanguageFile($datei);

        $text = $GLOBALS['TL_LANG'][$datei][$schluessel] ?? $schluessel;

        if (!\is_string($text)) {
            return $schluessel;
        }

        return [] === $werte ? $text : vsprintf($text, $werte);
    }
}
