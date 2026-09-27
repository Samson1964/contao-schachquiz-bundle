<?php

declare(strict_types=1);

/*
 * Contao Schachquiz Bundle.
 *
 * @license LGPL-3.0-or-later
 */

namespace Schachbulle\ContaoSchachquizBundle\EventListener\DataContainer;

use Contao\DataContainer;
use Contao\Input;
use Contao\StringUtil;
use Contao\System;
use Doctrine\DBAL\Connection;
use Schachbulle\ContaoSchachquizBundle\Import\FragenLeser;
use Schachbulle\ContaoSchachquizBundle\Schach\Fen;
use Schachbulle\ContaoSchachquizBundle\Sprache;
use Schachbulle\ContaoSchachquizBundle\Wertung\Schwierigkeit;

/**
 * Rückrufe der Tabelle tl_schachquiz_items (die Fragen eines Themas).
 *
 * Registriert über den Dienst-Tag `contao.callback` in der services.yaml.
 */
class FragenListener
{
    /**
     * Legt den Listener an.
     *
     * @param Connection $db Datenbankverbindung von Contao
     */
    public function __construct(private readonly Connection $db)
    {
    }

    /**
     * Stellt eine Frage in der Kindliste des Themas dar.
     *
     * Neben dem Fragetext zeigt die Zeile Typ, redaktionelle Stufe, die
     * aktuelle Wertung und die Trefferquote. Weichen Stufe und Wertung
     * deutlich voneinander ab, ist die Frage leichter oder schwerer als
     * gedacht — das soll der Redakteur auf einen Blick sehen.
     *
     * @param array<string, mixed> $zeile Die Datenbankzeile der Frage
     *
     * @return string HTML der Listenzeile
     */
    public function listenzeile(array $zeile): string
    {
        $anzahl = (int) $zeile['anzahl'];
        $quote = $anzahl > 0 ? sprintf('%d %%', round(100 * (int) $zeile['anzahl_richtig'] / $anzahl)) : '–';
        $stufeJetzt = Schwierigkeit::stufe((float) $zeile['wertung']);

        $angaben = Sprache::text(
            'tl_schachquiz_items',
            'listenzeile',
            Sprache::text('tl_schachquiz_items', 'multiple' === $zeile['typ'] ? 'listeMehrfach' : 'listeEinfach'),
            (int) $zeile['schwierigkeit'],
            $anzahl > 0 && $stufeJetzt !== (int) $zeile['schwierigkeit'] ? Sprache::text('tl_schachquiz_items', 'listeTatsaechlich', $stufeJetzt) : '',
            (int) round((float) $zeile['wertung']),
            $anzahl,
            $quote,
            '' !== (string) $zeile['fen'] ? Sprache::text('tl_schachquiz_items', 'listeStellung') : ''
        );

        return sprintf(
            '<div class="tl_content_left">%s <span style="color:#999;padding-left:3px">[%s]</span></div>',
            StringUtil::specialchars(StringUtil::substr((string) $zeile['frage'], 120)),
            $angaben
        );
    }

    /**
     * Prüft und vervollständigt die Stellung beim Speichern.
     *
     * @param mixed         $wert Der eingegebene Wert
     * @param DataContainer $dc   Der DataContainer; wird nicht gebraucht
     *
     * @throws \InvalidArgumentException Bei einer ungültigen FEN; Contao zeigt
     *                                   die Meldung am Feld an
     *
     * @return string Die vollständige FEN oder eine leere Zeichenkette
     */
    public function pruefeFen($wert, DataContainer $dc): string
    {
        $wert = trim((string) $wert);

        return '' === $wert ? '' : Fen::pruefe($wert);
    }

    /**
     * Prüft die Markierung der richtigen Antworten beim Speichern.
     *
     * Die übrigen Felder (Typ, Antworttexte) stehen in diesem Moment noch
     * nicht in der Datenbank, sondern nur im abgeschickten Formular; sie
     * werden deshalb über Input::post gelesen.
     *
     * @param mixed         $wert Der serialisierte Wert des Kontrollkästchens
     * @param DataContainer $dc   Der DataContainer; wird nicht gebraucht
     *
     * @throws \InvalidArgumentException Wenn keine, eine leere oder bei
     *                                   Einfachauswahl mehr als eine Antwort
     *                                   markiert ist
     *
     * @return mixed Der unveränderte Wert
     */
    public function pruefeRichtig($wert, DataContainer $dc)
    {
        $markiert = array_map('intval', StringUtil::deserialize($wert, true));

        if ([] === $markiert) {
            throw new \InvalidArgumentException(Sprache::text('tl_schachquiz_items', 'fehlerKeineRichtige'));
        }

        $gefuellt = 0;

        for ($i = 1; $i <= FragenLeser::MAX_ANTWORTEN; ++$i) {
            $text = trim((string) Input::post('antwort'.$i));

            if ('' !== $text) {
                ++$gefuellt;
            } elseif (\in_array($i, $markiert, true)) {
                throw new \InvalidArgumentException(Sprache::text('tl_schachquiz_items', 'fehlerLeereRichtige', $i));
            }
        }

        if ($gefuellt < 2) {
            throw new \InvalidArgumentException(Sprache::text('tl_schachquiz_items', 'fehlerZweiAntworten'));
        }

        if ('multiple' !== Input::post('typ') && \count($markiert) > 1) {
            throw new \InvalidArgumentException(Sprache::text('tl_schachquiz_items', 'fehlerNurEine'));
        }

        return $wert;
    }

    /**
     * Setzt die Anfangswertung einer Frage nach ihrer Stufe.
     *
     * Solange noch niemand die Frage beantwortet hat, folgt die Wertung der
     * redaktionellen Stufe — auch wenn der Redakteur die Stufe nachträglich
     * ändert. Ab der ersten Antwort gehört die Wertung dem Glicko-System;
     * eine Änderung der Stufe wirkt sich dann nicht mehr aus.
     *
     * Läuft als onsubmit_callback, also nach dem Schreiben der Felder:
     * Unter Contao 5 würde ein UPDATE im save_callback vom gesammelten
     * UPDATE der Maske überschrieben.
     *
     * @param DataContainer $dc Der DataContainer der gespeicherten Frage
     */
    public function setzeAnfangswertung(DataContainer $dc): void
    {
        if (!$dc->id) {
            return;
        }

        $zeile = $this->db->fetchAssociative('SELECT schwierigkeit, anzahl FROM tl_schachquiz_items WHERE id = ?', [$dc->id]);

        if (false === $zeile || (int) $zeile['anzahl'] > 0) {
            return;
        }

        $stand = Schwierigkeit::anfangsstand((int) $zeile['schwierigkeit']);

        $this->db->update('tl_schachquiz_items', $stand->alsZeile(), ['id' => $dc->id]);
    }

    /**
     * Gibt die wählbaren Stufen mit ihrer Anfangswertung zurück.
     *
     * @return array<int, string> Stufe => Beschriftung, etwa „5 – mittel (1500)"
     */
    public function stufenOptionen(): array
    {
        System::loadLanguageFile('tl_schachquiz_items');
        $namen = (array) ($GLOBALS['TL_LANG']['tl_schachquiz_items']['stufenNamen'] ?? []);
        $optionen = [];

        for ($stufe = Schwierigkeit::MIN; $stufe <= Schwierigkeit::MAX; ++$stufe) {
            $optionen[$stufe] = Sprache::text('tl_schachquiz_items', 'stufeOption', $stufe, isset($namen[$stufe]) ? ' – '.$namen[$stufe] : '', (int) Schwierigkeit::wertung($stufe));
        }

        return $optionen;
    }

    /**
     * Beschriftet die Kontrollkästchen „richtig" mit den Antworttexten.
     *
     * In der Maske sieht der Redakteur so „Antwort 2: Sizilianisch" statt
     * nur „Antwort 2". Die Texte stammen aus dem gespeicherten Datensatz;
     * bei einer neuen Frage bleiben es die nackten Nummern.
     *
     * @param DataContainer|null $dc Der DataContainer der bearbeiteten Frage
     *
     * @return array<int, string> Nummer => Beschriftung
     */
    public function richtigOptionen(?DataContainer $dc = null): array
    {
        $zeile = $dc && $dc->id
            ? $this->db->fetchAssociative('SELECT * FROM tl_schachquiz_items WHERE id = ?', [$dc->id])
            : false;

        $optionen = [];

        for ($i = 1; $i <= FragenLeser::MAX_ANTWORTEN; ++$i) {
            $text = \is_array($zeile) ? trim((string) $zeile['antwort'.$i]) : '';
            $optionen[$i] = Sprache::text('tl_schachquiz_items', 'antwortOption', $i).('' !== $text ? ': '.StringUtil::substr($text, 60) : '');
        }

        return $optionen;
    }
}
