<?php

declare(strict_types=1);

/*
 * Contao Schachquiz Bundle.
 *
 * @license LGPL-3.0-or-later
 */

namespace Schachbulle\ContaoSchachquizBundle\Import;

use Schachbulle\ContaoSchachquizBundle\Schach\Fen;
use Schachbulle\ContaoSchachquizBundle\Wertung\Schwierigkeit;

/**
 * Liest Quizfragen aus CSV- oder JSON-Dateien und prüft sie.
 *
 * Die Klasse schreibt nichts in die Datenbank; sie liefert ein
 * Importergebnis, das FragenSpeicher anschließend übernimmt. Das trennt die
 * fehleranfällige Deutung fremder Dateien vom Speichern und macht sie ohne
 * Contao prüfbar.
 *
 * Eine eingelesene Frage hat stets diese Form:
 *
 *     frage         string       Fragetext
 *     typ           string       „single" oder „multiple"
 *     schwierigkeit int          1 bis 10
 *     fen           string       vollständige FEN oder leer
 *     brett         string       Brettansicht: „auto“, „weiss“ oder „schwarz“
 *     antworten     list<string> zwei bis sechs Antworttexte
 *     richtig       list<int>    Nummern der richtigen Antworten, ab 1
 *     erklaerung    string       Erläuterung nach dem Antworten, darf leer sein
 */
final class FragenLeser
{
    /** Höchstzahl der Antwortmöglichkeiten; entspricht den Feldern der Tabelle. */
    public const MAX_ANTWORTEN = 6;

    /**
     * Liest eine Datei und entscheidet anhand der Endung über das Format.
     *
     * @param string $inhalt    Der rohe Dateiinhalt
     * @param string $dateiname Der ursprüngliche Dateiname, nur für die Endung
     *
     * @return Importergebnis Die gültigen Fragen und die Fehlerliste; bei einer
     *                        unbekannten Endung nur eine Fehlermeldung
     */
    public function lese(string $inhalt, string $dateiname): Importergebnis
    {
        $endung = strtolower(pathinfo($dateiname, PATHINFO_EXTENSION));

        if ('json' === $endung) {
            return $this->leseJson($inhalt);
        }

        if ('csv' === $endung || 'txt' === $endung) {
            return $this->leseCsv($inhalt);
        }

        $ergebnis = new Importergebnis();
        $ergebnis->fehler[] = sprintf('Unbekanntes Dateiformat „.%s". Erlaubt sind .csv und .json.', $endung);

        return $ergebnis;
    }

    /**
     * Liest Fragen aus einer CSV-Datei.
     *
     * Die erste Zeile muss die Spaltennamen enthalten; die Reihenfolge ist
     * beliebig, Groß- und Kleinschreibung sowie Leerzeichen zählen nicht.
     * Bekannte Spalten: thema, beschreibung, frage, typ, schwierigkeit, fen,
     * antwort1 bis antwort6, richtig, erklaerung (auch „erklärung").
     *
     * Das Trennzeichen (Semikolon, Komma oder Tabulator) wird aus der
     * Kopfzeile erraten. Excel speichert CSV unter deutschem Windows als
     * Windows-1252 mit Semikolon; solche Dateien werden nach UTF-8 gewandelt.
     *
     * @param string $inhalt Der rohe Dateiinhalt
     *
     * @return Importergebnis Die gültigen Fragen und die Fehlerliste
     */
    public function leseCsv(string $inhalt): Importergebnis
    {
        $ergebnis = new Importergebnis();
        $inhalt = $this->alsUtf8($inhalt);

        $ersteZeile = strtok($inhalt, "\r\n");

        if (false === $ersteZeile || '' === trim($ersteZeile)) {
            $ergebnis->fehler[] = 'Die Datei ist leer.';

            return $ergebnis;
        }

        $trenner = $this->errateTrenner($ersteZeile);

        // fgetcsv beherrscht Anführungszeichen und Zeilenumbrüche in Zellen;
        // str_getcsv zeilenweise würde mehrzeilige Erklärungen zerreißen.
        $datei = fopen('php://temp', 'r+');
        fwrite($datei, $inhalt);
        rewind($datei);

        $kopf = fgetcsv($datei, 0, $trenner, '"', '');
        $spalten = [];

        foreach ($kopf ?: [] as $index => $name) {
            $spalten[$this->spaltenname((string) $name)] = $index;
        }

        if (!isset($spalten['frage'])) {
            $ergebnis->fehler[] = 'Die Kopfzeile enthält keine Spalte „frage". Die erste Zeile muss die Spaltennamen enthalten.';
            fclose($datei);

            return $ergebnis;
        }

        $zeilennummer = 1;

        while (false !== ($zeile = fgetcsv($datei, 0, $trenner, '"', ''))) {
            ++$zeilennummer;

            // Leerzeilen, wie Excel sie gern ans Ende hängt, still übergehen.
            if ([null] === $zeile || '' === trim(implode('', array_map('strval', $zeile)))) {
                continue;
            }

            $wert = static fn (string $name): string => isset($spalten[$name]) ? trim((string) ($zeile[$spalten[$name]] ?? '')) : '';

            $antworten = [];

            for ($i = 1; $i <= self::MAX_ANTWORTEN; ++$i) {
                $antworten[] = $wert('antwort'.$i);
            }

            $roh = [
                'frage' => $wert('frage'),
                'typ' => $wert('typ'),
                'schwierigkeit' => $wert('schwierigkeit'),
                'fen' => $wert('fen'),
                'brett' => $wert('brett'),
                'antworten' => $antworten,
                'richtig' => $this->richtigAusText($wert('richtig')),
                'erklaerung' => $wert('erklaerung'),
            ];

            $this->uebernimm($ergebnis, $roh, $wert('thema'), $wert('beschreibung'), sprintf('Zeile %d', $zeilennummer));
        }

        fclose($datei);

        return $ergebnis;
    }

    /**
     * Liest Fragen aus einer JSON-Datei.
     *
     * Erlaubt sind drei Formen: ein Objekt mit „themen" (Liste von Themen mit
     * titel, beschreibung und fragen), ein Objekt mit „fragen" oder direkt
     * eine Liste von Fragen. Antworten sind entweder Objekte
     * `{"text": "…", "richtig": true}` oder Zeichenketten; im zweiten Fall
     * nennt die Frage die richtigen Nummern unter „richtig", etwa `[1, 3]`.
     *
     * @param string $inhalt Der rohe Dateiinhalt
     *
     * @return Importergebnis Die gültigen Fragen und die Fehlerliste
     */
    public function leseJson(string $inhalt): Importergebnis
    {
        $ergebnis = new Importergebnis();

        try {
            $daten = json_decode($this->alsUtf8($inhalt), true, 64, JSON_THROW_ON_ERROR);
        } catch (\JsonException $ausnahme) {
            $ergebnis->fehler[] = 'Die JSON-Datei ist nicht lesbar: '.$ausnahme->getMessage();

            return $ergebnis;
        }

        if (!\is_array($daten)) {
            $ergebnis->fehler[] = 'Die JSON-Datei enthält weder Themen noch Fragen.';

            return $ergebnis;
        }

        if (array_is_list($daten)) {
            $themen = [['titel' => '', 'fragen' => $daten]];
        } elseif (isset($daten['themen']) && \is_array($daten['themen'])) {
            $themen = $daten['themen'];
        } elseif (isset($daten['fragen']) && \is_array($daten['fragen'])) {
            $themen = [['titel' => $daten['titel'] ?? '', 'beschreibung' => $daten['beschreibung'] ?? '', 'fragen' => $daten['fragen']]];
        } else {
            $ergebnis->fehler[] = 'Die JSON-Datei enthält weder „themen" noch „fragen".';

            return $ergebnis;
        }

        foreach ($themen as $t => $thema) {
            if (!\is_array($thema) || !\is_array($thema['fragen'] ?? null)) {
                $ergebnis->fehler[] = sprintf('Thema %d: keine Liste „fragen".', $t + 1);

                continue;
            }

            $titel = \is_string($thema['titel'] ?? null) ? $thema['titel'] : '';
            $beschreibung = \is_string($thema['beschreibung'] ?? null) ? $thema['beschreibung'] : '';

            foreach ($thema['fragen'] as $f => $frage) {
                $ort = sprintf('%sFrage %d', '' !== $titel ? 'Thema „'.$titel.'", ' : '', $f + 1);

                if (!\is_array($frage)) {
                    $ergebnis->fehler[] = $ort.': Die Frage ist kein Objekt.';

                    continue;
                }

                $antworten = [];
                $richtig = [];

                foreach (\is_array($frage['antworten'] ?? null) ? array_values($frage['antworten']) : [] as $a => $antwort) {
                    if (\is_array($antwort)) {
                        $antworten[] = trim((string) ($antwort['text'] ?? ''));

                        if (!empty($antwort['richtig'])) {
                            $richtig[] = $a + 1;
                        }
                    } else {
                        $antworten[] = trim((string) $antwort);
                    }
                }

                if (isset($frage['richtig'])) {
                    $richtig = array_merge($richtig, \is_array($frage['richtig'])
                        ? $this->richtigAusText(implode(',', array_map('strval', $frage['richtig'])))
                        : $this->richtigAusText((string) $frage['richtig']));
                }

                $roh = [
                    'frage' => trim((string) ($frage['frage'] ?? '')),
                    'typ' => trim((string) ($frage['typ'] ?? '')),
                    'schwierigkeit' => trim((string) ($frage['schwierigkeit'] ?? '')),
                    'fen' => trim((string) ($frage['fen'] ?? '')),
                    'brett' => trim((string) ($frage['brett'] ?? '')),
                    'antworten' => $antworten,
                    'richtig' => array_values(array_unique($richtig)),
                    'erklaerung' => trim((string) ($frage['erklaerung'] ?? $frage['erklärung'] ?? '')),
                ];

                $this->uebernimm($ergebnis, $roh, $titel, $beschreibung, $ort);
            }
        }

        return $ergebnis;
    }

    /**
     * Prüft eine rohe Frage und ordnet sie ihrem Thema zu.
     *
     * Leere Antwortfelder zwischen gefüllten werden entfernt und die
     * Nummern der richtigen Antworten entsprechend nachgezogen — in einer
     * CSV-Datei bleibt „antwort3" gern einmal leer, während „antwort4" gefüllt ist.
     *
     * @param Importergebnis       $ergebnis     Nimmt die Frage oder den Fehler auf
     * @param array<string, mixed> $roh          Frage in der Form aus der Klassenbeschreibung,
     *                                           aber noch ungeprüft
     * @param string               $thema        Titel des Themas, leer für „ohne Thema"
     * @param string               $beschreibung Beschreibung des Themas
     * @param string               $ort          Fundstelle für Fehlermeldungen
     */
    private function uebernimm(Importergebnis $ergebnis, array $roh, string $thema, string $beschreibung, string $ort): void
    {
        $fehler = [];

        if ('' === $roh['frage']) {
            $fehler[] = 'Der Fragetext fehlt.';
        }

        // Leere Antworten entfernen und die richtigen Nummern umschreiben.
        $antworten = [];
        $umnummerierung = [];

        foreach ($roh['antworten'] as $index => $text) {
            if ('' !== $text) {
                $antworten[] = $text;
                $umnummerierung[$index + 1] = \count($antworten);
            }
        }

        $richtig = [];

        foreach ($roh['richtig'] as $nummer) {
            if (!isset($umnummerierung[$nummer])) {
                $fehler[] = sprintf('Antwort %d ist als richtig markiert, aber leer oder nicht vorhanden.', $nummer);
            } else {
                $richtig[] = $umnummerierung[$nummer];
            }
        }

        sort($richtig);

        if (\count($antworten) < 2) {
            $fehler[] = 'Es werden mindestens zwei Antworten gebraucht.';
        }

        if (\count($antworten) > self::MAX_ANTWORTEN) {
            $fehler[] = sprintf('Höchstens %d Antworten sind möglich.', self::MAX_ANTWORTEN);
        }

        if ([] === $richtig && [] === $fehler) {
            $fehler[] = 'Keine richtige Antwort markiert.';
        }

        $typ = $this->typ($roh['typ'], \count($richtig));

        if (null === $typ) {
            $fehler[] = sprintf('Unbekannter Fragetyp „%s" (erlaubt: single, multiple).', $roh['typ']);
        } elseif ('single' === $typ && \count($richtig) > 1) {
            $fehler[] = 'Eine Single-Choice-Frage darf nur eine richtige Antwort haben.';
        }

        $schwierigkeit = 5;

        if ('' !== $roh['schwierigkeit']) {
            if (!ctype_digit($roh['schwierigkeit'])
                || (int) $roh['schwierigkeit'] < Schwierigkeit::MIN
                || (int) $roh['schwierigkeit'] > Schwierigkeit::MAX) {
                $fehler[] = sprintf('Die Schwierigkeit muss zwischen %d und %d liegen.', Schwierigkeit::MIN, Schwierigkeit::MAX);
            } else {
                $schwierigkeit = (int) $roh['schwierigkeit'];
            }
        }

        $fen = '';

        if ('' !== $roh['fen']) {
            try {
                $fen = Fen::pruefe($roh['fen']);
            } catch (\InvalidArgumentException $ausnahme) {
                $fehler[] = 'Stellung: '.$ausnahme->getMessage();
            }
        }

        $brett = $this->brett($roh['brett']);

        if (null === $brett) {
            $fehler[] = sprintf('Unbekannte Brettansicht „%s“ (erlaubt: auto, weiss, schwarz).', $roh['brett']);
        }

        if ([] !== $fehler) {
            foreach ($fehler as $meldung) {
                $ergebnis->fehler[] = $ort.': '.$meldung;
            }

            return;
        }

        $schluessel = $ergebnis->thema($thema, $beschreibung);
        $ergebnis->themen[$schluessel]['fragen'][] = [
            'frage' => $roh['frage'],
            'typ' => $typ,
            'schwierigkeit' => $schwierigkeit,
            'fen' => $fen,
            'brett' => $brett,
            'antworten' => $antworten,
            'richtig' => $richtig,
            'erklaerung' => $roh['erklaerung'],
        ];
    }

    /**
     * Deutet die Angabe der richtigen Antworten.
     *
     * Erlaubt sind Nummern („1,3", „2") und Buchstaben („A;C", „b"), getrennt
     * durch Komma, Semikolon, Schrägstrich oder Leerzeichen.
     *
     * @param string $text Der Zellinhalt
     *
     * @return list<int> Die Nummern ab 1; unverständliche Teile ergeben 0 und
     *                   fallen später als „nicht vorhanden" auf
     */
    private function richtigAusText(string $text): array
    {
        $nummern = [];

        foreach (preg_split('/[\s,;\/]+/', trim($text), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $teil) {
            if (ctype_digit($teil)) {
                $nummern[] = (int) $teil;
            } elseif (1 === \strlen($teil) && ctype_alpha($teil)) {
                $nummern[] = \ord(strtolower($teil)) - \ord('a') + 1;
            } else {
                $nummern[] = 0;
            }
        }

        return array_values(array_unique($nummern));
    }

    /**
     * Bestimmt den Fragetyp aus der Angabe oder, wenn diese fehlt, aus der
     * Zahl der richtigen Antworten.
     *
     * @param string $angabe  Der Wert aus der Datei; deutsche und englische
     *                        Schreibweisen sowie Kürzel sind erlaubt
     * @param int    $richtig Anzahl der richtigen Antworten
     *
     * @return string|null „single", „multiple" oder null bei unbekannter Angabe
     */
    private function typ(string $angabe, int $richtig): ?string
    {
        $angabe = strtolower(str_replace([' ', '-', '_'], '', $angabe));

        if ('' === $angabe) {
            return $richtig > 1 ? 'multiple' : 'single';
        }

        return match ($angabe) {
            'single', 'singlechoice', 'sc', 'einfach', 'einfachauswahl', 'eine' => 'single',
            'multiple', 'multiplechoice', 'mc', 'mehrfach', 'mehrfachauswahl', 'mehrere' => 'multiple',
            default => null,
        };
    }

    /**
     * Deutet die Angabe der Brettansicht.
     *
     * @param string $angabe Der Wert aus der Datei; leer bedeutet „auto“
     *
     * @return string|null „auto“, „weiss“, „schwarz“ oder null bei unbekannter Angabe
     */
    private function brett(string $angabe): ?string
    {
        return match (strtolower(str_replace(['ß', ' '], ['ss', ''], $angabe))) {
            '', 'auto', 'automatisch' => 'auto',
            'weiss', 'weiß', 'white', 'w' => 'weiss',
            'schwarz', 'black', 'b' => 'schwarz',
            default => null,
        };
    }

    /**
     * Vereinheitlicht einen Spaltennamen aus der CSV-Kopfzeile.
     *
     * @param string $name Der Name, wie er in der Datei steht
     *
     * @return string Kleingeschrieben, ohne Leerzeichen und Unterstriche,
     *                „ä" als „ae" — also „Antwort 1" → „antwort1",
     *                „Erklärung" → „erklaerung"
     */
    private function spaltenname(string $name): string
    {
        $name = mb_strtolower(trim($name));
        $name = str_replace(['ä', 'ö', 'ü', 'ß', ' ', '_', '-'], ['ae', 'oe', 'ue', 'ss', '', '', ''], $name);

        return match ($name) {
            'question' => 'frage',
            'topic', 'kategorie' => 'thema',
            'difficulty', 'stufe', 'grad' => 'schwierigkeit',
            'correct', 'loesung' => 'richtig',
            'explanation' => 'erklaerung',
            'type', 'art' => 'typ',
            'stellung' => 'fen',
            'ansicht', 'brettansicht' => 'brett',
            default => $name,
        };
    }

    /**
     * Wandelt den Inhalt nach UTF-8 und entfernt eine Byte-Reihenfolge-Marke.
     *
     * Was kein gültiges UTF-8 ist, wird als Windows-1252 gedeutet — das ist
     * die Kodierung, in der Excel unter deutschem Windows CSV speichert.
     *
     * @param string $inhalt Der rohe Inhalt
     *
     * @return string Der Inhalt in UTF-8
     */
    private function alsUtf8(string $inhalt): string
    {
        if (str_starts_with($inhalt, "\xEF\xBB\xBF")) {
            $inhalt = substr($inhalt, 3);
        }

        if (!mb_check_encoding($inhalt, 'UTF-8')) {
            $inhalt = mb_convert_encoding($inhalt, 'UTF-8', 'Windows-1252');
        }

        return $inhalt;
    }

    /**
     * Errät das Trennzeichen aus der Kopfzeile.
     *
     * @param string $kopfzeile Die erste Zeile der Datei
     *
     * @return string Das häufigste von Semikolon, Tabulator und Komma;
     *                bei Gleichstand das Semikolon
     */
    private function errateTrenner(string $kopfzeile): string
    {
        $zaehlung = [
            ';' => substr_count($kopfzeile, ';'),
            "\t" => substr_count($kopfzeile, "\t"),
            ',' => substr_count($kopfzeile, ','),
        ];

        arsort($zaehlung);

        return (string) array_key_first($zaehlung);
    }
}
