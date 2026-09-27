<?php

declare(strict_types=1);

/*
 * Contao Schachquiz Bundle.
 *
 * @license LGPL-3.0-or-later
 */

namespace Schachbulle\ContaoSchachquizBundle\Import;

/**
 * Ergebnis des Einlesens einer Importdatei, noch vor dem Speichern.
 *
 * Fehlerhafte Fragen landen nicht in der Themenliste, sondern mit Fundstelle
 * in der Fehlerliste. So kann der Import die gültigen Fragen übernehmen und
 * dem Redakteur trotzdem genau sagen, welche Zeilen er nachbessern muss.
 */
final class Importergebnis
{
    /**
     * Eingelesene Themen, nach Titel geordnet. Der leere Titel steht für
     * Fragen ohne Themenangabe; sie landen später im gewählten Zielthema.
     *
     * @var array<string, array{titel: string, beschreibung: string, fragen: list<array<string, mixed>>}>
     */
    public array $themen = [];

    /**
     * Fehlermeldungen mit Fundstelle, etwa „Zeile 7: Keine richtige Antwort markiert."
     *
     * @var list<string>
     */
    public array $fehler = [];

    /**
     * Legt ein Thema an, falls es noch fehlt, und gibt dessen Schlüssel zurück.
     *
     * Eine Beschreibung überschreibt eine bereits vorhandene nur, wenn diese
     * leer ist; bei CSV steht die Beschreibung nämlich in jeder Zeile erneut
     * oder nur in der ersten.
     *
     * @param string $titel        Titel des Themas; leer für „ohne Thema"
     * @param string $beschreibung Optionale Beschreibung
     *
     * @return string Der Schlüssel in $themen, also der bereinigte Titel
     */
    public function thema(string $titel, string $beschreibung = ''): string
    {
        $titel = trim($titel);

        if (!isset($this->themen[$titel])) {
            $this->themen[$titel] = ['titel' => $titel, 'beschreibung' => '', 'fragen' => []];
        }

        if ('' === $this->themen[$titel]['beschreibung'] && '' !== trim($beschreibung)) {
            $this->themen[$titel]['beschreibung'] = trim($beschreibung);
        }

        return $titel;
    }

    /**
     * Zählt alle gültig eingelesenen Fragen über alle Themen.
     *
     * @return int Anzahl der Fragen, 0 wenn nichts gültig war
     */
    public function anzahlFragen(): int
    {
        $anzahl = 0;

        foreach ($this->themen as $thema) {
            $anzahl += \count($thema['fragen']);
        }

        return $anzahl;
    }

    /**
     * Sagt, ob mindestens eine Frage einer Themenangabe aus der Datei folgt.
     *
     * @return bool true, wenn es ein Thema mit nicht leerem Titel und Fragen gibt
     */
    public function hatBenannteThemen(): bool
    {
        foreach ($this->themen as $titel => $thema) {
            if ('' !== $titel && [] !== $thema['fragen']) {
                return true;
            }
        }

        return false;
    }
}
