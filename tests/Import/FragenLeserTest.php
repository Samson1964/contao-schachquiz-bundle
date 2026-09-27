<?php

declare(strict_types=1);

/*
 * Contao Schachquiz Bundle.
 *
 * @license LGPL-3.0-or-later
 */

namespace Schachbulle\ContaoSchachquizBundle\Tests\Import;

use PHPUnit\Framework\TestCase;
use Schachbulle\ContaoSchachquizBundle\Import\FragenLeser;

/**
 * Prüft das Einlesen von CSV- und JSON-Dateien, einschließlich der
 * mitgelieferten Beispieldateien.
 */
class FragenLeserTest extends TestCase
{
    private const BEISPIEL = __DIR__.'/../../src/Resources/beispiel/schachquiz-beispiel.';

    /**
     * Die mitgelieferte JSON-Datei ist fehlerfrei: 40 Fragen in fünf Themen,
     * alle Stellungen gültig.
     */
    public function testBeispielJson(): void
    {
        $ergebnis = (new FragenLeser())->lese((string) file_get_contents(self::BEISPIEL.'json'), 'x.json');

        $this->assertSame([], $ergebnis->fehler);
        $this->assertSame(40, $ergebnis->anzahlFragen());
        $this->assertSame(['Schachregeln', 'Schachgeschichte', 'Eröffnungen', 'Taktik', 'Endspiele'], array_keys($ergebnis->themen));

        $mitStellung = 0;

        foreach ($ergebnis->themen as $thema) {
            foreach ($thema['fragen'] as $frage) {
                $mitStellung += '' !== $frage['fen'] ? 1 : 0;
                $this->assertCount('single' === $frage['typ'] ? 1 : \count($frage['richtig']), $frage['richtig']);
            }
        }

        $this->assertSame(13, $mitStellung);
        $this->assertSame('weiss', $ergebnis->themen['Eröffnungen']['fragen'][0]['brett']);
        $this->assertSame('auto', $ergebnis->themen['Taktik']['fragen'][0]['brett']);
    }

    /**
     * Die mitgelieferte CSV-Datei ist fehlerfrei; Buchstaben als Lösung,
     * fehlender Typ und mehrzeilige Erklärung werden verstanden.
     */
    public function testBeispielCsv(): void
    {
        $ergebnis = (new FragenLeser())->lese((string) file_get_contents(self::BEISPIEL.'csv'), 'x.csv');

        $this->assertSame([], $ergebnis->fehler);
        $this->assertSame(4, $ergebnis->anzahlFragen());

        $steinitz = $ergebnis->themen['Schachgeschichte']['fragen'][0];
        $this->assertSame([2], $steinitz['richtig']);
        $this->assertSame('single', $steinitz['typ']);
        $this->assertStringContainsString("\n", $steinitz['erklaerung']);

        $umwandlung = $ergebnis->themen['Schachregeln']['fragen'][1];
        $this->assertSame('multiple', $umwandlung['typ']);
        $this->assertSame([1, 2, 3, 4], $umwandlung['richtig']);
    }

    /**
     * Excel-CSV: Windows-1252, Semikolon, abweichende Spaltennamen; eine
     * Lücke bei den Antworten wird geschlossen und die Lösung nachgezogen.
     */
    public function testExcelCsvMitLuecke(): void
    {
        $csv = "Frage;Antwort 1;Antwort 2;Antwort 3;Antwort 4;Lösung;Erklärung\r\n"
            ."Wer zieht zuerst?;Schwarz;;Weiß;;3;Weiß beginnt.\r\n\r\n";

        $ergebnis = (new FragenLeser())->leseCsv((string) mb_convert_encoding($csv, 'Windows-1252', 'UTF-8'));

        $this->assertSame([], $ergebnis->fehler);
        $frage = $ergebnis->themen['']['fragen'][0];
        $this->assertSame(['Schwarz', 'Weiß'], $frage['antworten']);
        $this->assertSame([2], $frage['richtig']);
        $this->assertSame('Weiß beginnt.', $frage['erklaerung']);
        $this->assertSame(5, $frage['schwierigkeit']);
    }

    /**
     * Fehlerhafte Zeilen landen mit Zeilennummer in der Fehlerliste, gültige
     * werden trotzdem übernommen.
     */
    public function testFehlerMitFundstelle(): void
    {
        $csv = "frage,typ,antwort1,antwort2,richtig,fen,schwierigkeit\n"
            ."Gut?,single,Ja,Nein,1,,3\n"
            ."Ohne Lösung?,single,Ja,Nein,,,\n"
            ."Zwei richtig?,single,Ja,Nein,\"1,2\",,\n"
            ."Kaputte Stellung?,,Ja,Nein,1,8/8/8,\n"
            ."Zu schwer?,,Ja,Nein,1,,11\n"
            ."Leere Lösung?,,Ja,Nein,4,,\n";

        $ergebnis = (new FragenLeser())->leseCsv($csv);

        $this->assertSame(1, $ergebnis->anzahlFragen());
        $this->assertCount(5, $ergebnis->fehler);
        $this->assertStringStartsWith('Zeile 3:', $ergebnis->fehler[0]);
        $this->assertStringStartsWith('Zeile 4:', $ergebnis->fehler[1]);
        $this->assertStringContainsString('Stellung', $ergebnis->fehler[2]);
        $this->assertStringStartsWith('Zeile 6:', $ergebnis->fehler[3]);
        $this->assertStringContainsString('Antwort 4', $ergebnis->fehler[4]);
    }

    /**
     * JSON als bloße Liste mit Antwortobjekten und Kurzformen.
     */
    public function testJsonListeMitAntwortobjekten(): void
    {
        $json = '[{"frage": "Welche Figuren sind Leichtfiguren?", "typ": "mc", "antworten": ['
            .'{"text": "Läufer", "richtig": true}, {"text": "Turm"}, {"text": "Springer", "richtig": true}], "brett": "Schwarz"}]';

        $ergebnis = (new FragenLeser())->leseJson($json);

        $this->assertSame([], $ergebnis->fehler);
        $frage = $ergebnis->themen['']['fragen'][0];
        $this->assertSame('multiple', $frage['typ']);
        $this->assertSame([1, 3], $frage['richtig']);
        $this->assertSame('schwarz', $frage['brett']);
    }

    /**
     * Unbekannte Endungen und kaputtes JSON werden gemeldet, nicht geworfen.
     */
    public function testUnlesbareDateien(): void
    {
        $leser = new FragenLeser();

        $this->assertStringContainsString('.xlsx', $leser->lese('', 'fragen.xlsx')->fehler[0]);
        $this->assertStringContainsString('nicht lesbar', $leser->lese('{kaputt', 'x.json')->fehler[0]);
        $this->assertStringContainsString('frage', $leser->lese("a;b\n1;2", 'x.csv')->fehler[0]);
    }
}
