<?php

declare(strict_types=1);

/*
 * Contao Schachquiz Bundle.
 *
 * @license LGPL-3.0-or-later
 */

namespace Schachbulle\ContaoSchachquizBundle\Backend;

use Contao\CoreBundle\Csrf\ContaoCsrfTokenManager;
use Contao\CoreBundle\Exception\RedirectResponseException;
use Contao\CoreBundle\Exception\ResponseException;
use Contao\Message;
use Contao\StringUtil;
use Contao\System;
use Doctrine\DBAL\Connection;
use Schachbulle\ContaoSchachquizBundle\Import\FragenLeser;
use Schachbulle\ContaoSchachquizBundle\Import\FragenSpeicher;
use Schachbulle\ContaoSchachquizBundle\Import\Importergebnis;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

/**
 * Backend-Seite „Fragen importieren" im Modul Schachquiz.
 *
 * Aufgerufen über `do=schachquiz&key=import`. Die Seite nimmt eine CSV- oder
 * JSON-Datei entgegen, spielt die mitgelieferten Beispielfragen ein oder
 * liefert die Vorlagen zum Herunterladen aus.
 *
 * Nach dem Absenden leitet die Seite auf sich selbst um (Post/Redirect/Get);
 * das Ergebnis überlebt die Umleitung als Contao-Meldung. So schickt ein
 * Neuladen der Seite die Datei nicht ein zweites Mal ab.
 */
class ImportModul
{
    /** Kennung des Formulars im Feld FORM_SUBMIT. */
    private const FORMULAR = 'schachquiz_import';

    /** Höchstzahl einzeln gemeldeter Fehler; der Rest wird nur gezählt. */
    private const MAX_FEHLERMELDUNGEN = 25;

    /**
     * Legt die Seite an.
     *
     * @param RequestStack           $requestStack Liefert die laufende Anfrage
     * @param FragenLeser            $leser        Liest und prüft die Datei
     * @param FragenSpeicher         $speicher     Schreibt die Fragen in die Datenbank
     * @param Connection             $db           Liest die Themen für die Zielauswahl
     * @param ContaoCsrfTokenManager $tokenManager Liefert den REQUEST_TOKEN für das Formular
     */
    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly FragenLeser $leser,
        private readonly FragenSpeicher $speicher,
        private readonly Connection $db,
        private readonly ContaoCsrfTokenManager $tokenManager,
    ) {
    }

    /**
     * Erzeugt die Importseite oder bearbeitet das abgeschickte Formular.
     *
     * Der Parameter ist untypisiert, weil Contao dem key-Rückruf je nach
     * Fassung ein DataContainer-Objekt oder gar nichts übergibt.
     *
     * @param mixed $dc Der DataContainer von tl_schachquiz; wird nicht gebraucht
     *
     * @throws RedirectResponseException Nach dem Absenden, zurück auf die Seite
     * @throws ResponseException         Beim Herunterladen einer Vorlage
     *
     * @return string Das HTML der Seite
     */
    public function zeige($dc = null): string
    {
        $request = $this->requestStack->getCurrentRequest();

        if (null === $request) {
            return '';
        }

        System::loadLanguageFile('tl_schachquiz');

        $vorlage = (string) $request->query->get('vorlage', '');

        if ('' !== $vorlage) {
            $this->liefereVorlage($vorlage);
        }

        if ($request->isMethod('POST') && self::FORMULAR === $request->request->get('FORM_SUBMIT')) {
            $this->bearbeite($request);

            throw new RedirectResponseException($request->getUri());
        }

        return $this->formular($request);
    }

    /**
     * Liest die hochgeladene Datei oder die Beispieldatei und speichert die Fragen.
     *
     * Das Ergebnis wird als Contao-Meldung abgelegt und nach der Umleitung
     * über dem Formular angezeigt.
     *
     * @param Request $request Die abgeschickte Anfrage
     */
    private function bearbeite(Request $request): void
    {
        $ziel = (int) $request->request->get('ziel', 0);

        if ($request->request->get('beispiel')) {
            $pfad = $this->vorlagenpfad('json');
            $ergebnis = $this->leser->lese((string) file_get_contents($pfad), basename($pfad));
        } else {
            $datei = $request->files->get('datei');

            if (!$datei instanceof UploadedFile || !$datei->isValid()) {
                Message::addError($this->text('keineDatei').($datei instanceof UploadedFile ? ' ('.$datei->getErrorMessage().')' : ''));

                return;
            }

            $ergebnis = $this->leser->lese((string) file_get_contents($datei->getPathname()), $datei->getClientOriginalName());
        }

        $this->meldeFehler($ergebnis);

        if (0 === $ergebnis->anzahlFragen()) {
            Message::addError($this->text('nichtsGueltig'));

            return;
        }

        try {
            $zaehler = $this->speicher->speichere($ergebnis, $ziel);
        } catch (\Throwable $ausnahme) {
            Message::addError($this->text('abgebrochen', $ausnahme->getMessage()));

            return;
        }

        Message::addConfirmation(
            $this->text('erfolg', $zaehler['fragen'])
            .($zaehler['neueThemen'] > 0 ? $this->text('neueThemen', $zaehler['neueThemen']) : '')
            .($zaehler['uebersprungen'] > 0 ? $this->text('uebersprungen', $zaehler['uebersprungen']) : '')
        );
    }

    /**
     * Meldet die Fehler des Einlesens, höchstens MAX_FEHLERMELDUNGEN einzeln.
     *
     * @param Importergebnis $ergebnis Das Ergebnis des Lesers
     */
    private function meldeFehler(Importergebnis $ergebnis): void
    {
        foreach (\array_slice($ergebnis->fehler, 0, self::MAX_FEHLERMELDUNGEN) as $fehler) {
            Message::addError($fehler);
        }

        $rest = \count($ergebnis->fehler) - self::MAX_FEHLERMELDUNGEN;

        if ($rest > 0) {
            Message::addError($this->text('weitereFehler', $rest));
        }
    }

    /**
     * Liefert eine Vorlagendatei zum Herunterladen aus.
     *
     * @param string $format „csv" oder „json"; alles andere wird ignoriert
     *
     * @throws ResponseException Mit der Datei als Anhang
     */
    private function liefereVorlage(string $format): void
    {
        if ('csv' !== $format && 'json' !== $format) {
            return;
        }

        $antwort = new BinaryFileResponse($this->vorlagenpfad($format));
        $antwort->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, 'schachquiz-vorlage.'.$format);

        throw new ResponseException($antwort);
    }

    /**
     * Gibt den Pfad einer mitgelieferten Beispieldatei zurück.
     *
     * @param string $format „csv" oder „json"
     *
     * @return string Absoluter Pfad im Bundle
     */
    private function vorlagenpfad(string $format): string
    {
        return \dirname(__DIR__).'/Resources/beispiel/schachquiz-beispiel.'.$format;
    }

    /**
     * Baut das Formular.
     *
     * Die Markierung folgt den Klassen des Contao-Backends (tl_form,
     * widget, tl_submit), damit die Seite in beiden Fassungen wie eine
     * gewöhnliche Bearbeitungsmaske aussieht.
     *
     * @param Request $request Die laufende Anfrage, für die Adressen
     *
     * @return string Das HTML
     */
    private function formular(Request $request): string
    {
        $basis = $request->getBaseUrl().$request->getPathInfo();
        $parameter = ['do' => 'schachquiz', 'key' => 'import', 'ref' => $request->attributes->get('_contao_referer_id')];
        $zurueck = $basis.'?'.http_build_query(['do' => 'schachquiz', 'ref' => $parameter['ref']]);
        $vorlageCsv = $basis.'?'.http_build_query($parameter + ['vorlage' => 'csv']);
        $vorlageJson = $basis.'?'.http_build_query($parameter + ['vorlage' => 'json']);

        $optionen = '<option value="0">'.StringUtil::specialchars($this->text('ausDatei')).'</option>';

        foreach ($this->db->fetchAllAssociative('SELECT id, title FROM tl_schachquiz ORDER BY title') as $thema) {
            $optionen .= sprintf('<option value="%d">%s</option>', $thema['id'], StringUtil::specialchars($thema['title']));
        }

        $e = static fn (string $text): string => StringUtil::specialchars($text);
        $t = fn (string $schluessel): string => $e($this->text($schluessel));
        $zurueckText = $e($GLOBALS['TL_LANG']['MSC']['backBT'] ?? 'Zurück');

        return '
<div id="tl_buttons">
  <a href="'.$e($zurueck).'" class="header_back" title="'.$zurueckText.'" accesskey="b">'.$zurueckText.'</a>
</div>
'.Message::generate().'
<form method="post" enctype="multipart/form-data" class="tl_form tl_edit_form" id="'.self::FORMULAR.'">
<div class="tl_formbody_edit">
  <input type="hidden" name="FORM_SUBMIT" value="'.self::FORMULAR.'">
  <input type="hidden" name="REQUEST_TOKEN" value="'.$e($this->tokenManager->getDefaultTokenValue()).'">
  <fieldset class="tl_tbox nolegend">
    <div class="widget w50">
      <h3><label for="schachquiz_datei">'.$t('datei').'</label></h3>
      <input type="file" name="datei" id="schachquiz_datei" accept=".csv,.json,.txt" class="tl_upload_field">
      <p class="tl_help tl_tip">'.$t('dateiHilfe').'</p>
    </div>
    <div class="widget w50">
      <h3><label for="schachquiz_ziel">'.$t('ziel').'</label></h3>
      <select name="ziel" id="schachquiz_ziel" class="tl_select">'.$optionen.'</select>
      <p class="tl_help tl_tip">'.$t('zielHilfe').'</p>
    </div>
    <div class="widget clr">
      <h3>'.$t('vorlagen').'</h3>
      <p><a href="'.$e($vorlageCsv).'">'.$t('vorlageCsv').'</a> · <a href="'.$e($vorlageJson).'">'.$t('vorlageJson').'</a></p>
      <p class="tl_help">'.$t('spalten').'</p>
    </div>
  </fieldset>
</div>
<div class="tl_formbody_submit">
  <div class="tl_submit_container">
    <button type="submit" class="tl_submit">'.$t('absenden').'</button>
    <button type="submit" name="beispiel" value="1" class="tl_submit" formnovalidate>'.$t('beispiel').'</button>
  </div>
</div>
</form>';
    }

    /**
     * Liest einen Text der Importseite aus der Sprachdatei tl_schachquiz.
     *
     * Die Schlüssel tragen dort das Präfix „import_“. Fehlt ein Text, wird
     * der Schlüssel selbst gezeigt, damit die Lücke auffällt statt eine
     * leere Stelle zu hinterlassen.
     *
     * @param string     $schluessel Schlüssel ohne Präfix
     * @param int|string ...$werte    Werte für die Platzhalter im Text (sprintf)
     *
     * @return string Der fertige Text
     */
    private function text(string $schluessel, int|string ...$werte): string
    {
        $text = $GLOBALS['TL_LANG']['tl_schachquiz']['import_'.$schluessel] ?? $schluessel;

        return [] === $werte ? $text : vsprintf($text, $werte);
    }
}
