<?php

declare(strict_types=1);

/*
 * Contao Schachquiz Bundle.
 *
 * @license LGPL-3.0-or-later
 */

$GLOBALS['TL_LANG']['tl_schachquiz']['title_legend'] = 'Thema';
$GLOBALS['TL_LANG']['tl_schachquiz']['publish_legend'] = 'Veröffentlichung';

$GLOBALS['TL_LANG']['tl_schachquiz']['title'] = ['Titel', 'Name des Themas, etwa „Schachregeln" oder „Eröffnungen". Er erscheint im Frontend als Auswahl.'];
$GLOBALS['TL_LANG']['tl_schachquiz']['titel_frontend'] = ['Titel im Frontend', 'Optional. Wird Besuchern statt des Titels gezeigt; im Backend bleibt der Titel maßgeblich.'];
$GLOBALS['TL_LANG']['tl_schachquiz']['listeFrontendtitel'] = 'im Frontend: %s';
$GLOBALS['TL_LANG']['tl_schachquiz']['beschreibung'] = ['Beschreibung', 'Optionale Beschreibung für die Redaktion.'];
$GLOBALS['TL_LANG']['tl_schachquiz']['published'] = ['Veröffentlicht', 'Nur Fragen veröffentlichter Themen erscheinen im Quiz.'];

$GLOBALS['TL_LANG']['tl_schachquiz']['new'] = ['Neues Thema', 'Ein neues Quizthema anlegen'];
$GLOBALS['TL_LANG']['tl_schachquiz']['wertungen'] = ['Quiz-Wertungen', 'Glicko-2-Wertungen der Mitglieder ansehen und zurücksetzen'];
$GLOBALS['TL_LANG']['tl_schachquiz']['monatsranglisten'] = ['Monatsranglisten', 'Die am Monatsersten gesicherten Ranglisten ansehen'];
$GLOBALS['TL_LANG']['tl_schachquiz']['import'] = ['Fragen importieren', 'Fragen aus einer CSV- oder JSON-Datei importieren'];
$GLOBALS['TL_LANG']['tl_schachquiz']['edit'] = ['Fragen bearbeiten', 'Fragen des Themas ID %s bearbeiten'];
$GLOBALS['TL_LANG']['tl_schachquiz']['editheader'] = ['Thema bearbeiten', 'Einstellungen des Themas ID %s bearbeiten'];
$GLOBALS['TL_LANG']['tl_schachquiz']['copy'] = ['Thema duplizieren', 'Thema ID %s duplizieren'];
$GLOBALS['TL_LANG']['tl_schachquiz']['delete'] = ['Thema löschen', 'Thema ID %s samt Fragen löschen'];
$GLOBALS['TL_LANG']['tl_schachquiz']['toggle'] = ['Thema veröffentlichen/verstecken', 'Thema ID %s veröffentlichen oder verstecken'];
$GLOBALS['TL_LANG']['tl_schachquiz']['show'] = ['Details', 'Details des Themas ID %s anzeigen'];

// Importseite (Backend\ImportModul)
$GLOBALS['TL_LANG']['tl_schachquiz']['import_datei'] = 'Datei (CSV oder JSON)';
$GLOBALS['TL_LANG']['tl_schachquiz']['import_dateiHilfe'] = 'CSV mit Kopfzeile (Semikolon, Komma oder Tabulator, UTF-8 oder Excel-Kodierung) oder JSON.';
$GLOBALS['TL_LANG']['tl_schachquiz']['import_ziel'] = 'Zielthema';
$GLOBALS['TL_LANG']['tl_schachquiz']['import_zielHilfe'] = 'Ohne Zielthema werden die Themen aus der Spalte „thema“ bzw. dem JSON-Feld „themen“ angelegt oder ergänzt.';
$GLOBALS['TL_LANG']['tl_schachquiz']['import_ausDatei'] = 'Themen aus der Datei übernehmen';
$GLOBALS['TL_LANG']['tl_schachquiz']['import_vorlagen'] = 'Vorlagen';
$GLOBALS['TL_LANG']['tl_schachquiz']['import_vorlageCsv'] = 'CSV-Vorlage herunterladen';
$GLOBALS['TL_LANG']['tl_schachquiz']['import_vorlageJson'] = 'JSON-Vorlage herunterladen';
$GLOBALS['TL_LANG']['tl_schachquiz']['import_spalten'] = 'Spalten der CSV-Datei: thema, beschreibung, frage, typ (single/multiple), schwierigkeit (1–10), fen, brett (auto/weiss/schwarz), antwort1 … antwort6, richtig (z. B. „2“ oder „1,3“), erklaerung. Fragen mit gleichem Text und gleicher Stellung im selben Thema werden übersprungen, dieselbe Datei lässt sich also gefahrlos erneut einspielen.';
$GLOBALS['TL_LANG']['tl_schachquiz']['import_absenden'] = 'Importieren';
$GLOBALS['TL_LANG']['tl_schachquiz']['import_beispiel'] = 'Mitgelieferte Beispielfragen einspielen';
$GLOBALS['TL_LANG']['tl_schachquiz']['import_keineDatei'] = 'Bitte eine CSV- oder JSON-Datei auswählen.';
$GLOBALS['TL_LANG']['tl_schachquiz']['import_nichtsGueltig'] = 'Die Datei enthielt keine gültige Frage. Es wurde nichts importiert.';
$GLOBALS['TL_LANG']['tl_schachquiz']['import_abgebrochen'] = 'Der Import wurde abgebrochen, es wurde nichts gespeichert: %s';
$GLOBALS['TL_LANG']['tl_schachquiz']['import_erfolg'] = '%d Fragen importiert';
$GLOBALS['TL_LANG']['tl_schachquiz']['import_neueThemen'] = ', %d neue Themen angelegt';
$GLOBALS['TL_LANG']['tl_schachquiz']['import_uebersprungen'] = ', %d bereits vorhandene Fragen übersprungen';
$GLOBALS['TL_LANG']['tl_schachquiz']['import_weitereFehler'] = '… und %d weitere Fehler.';

// Statistik (do=schachquiz&key=statistik)
$GLOBALS['TL_LANG']['tl_schachquiz']['statistik'] = ['Statistik', 'Gestellte und beantwortete Fragen auswerten'];
$GLOBALS['TL_LANG']['tl_schachquiz']['statistik_seite'] = [
    'zurueckModul' => 'Zurück',
    'ueberschrift' => 'Statistik der gestellten und beantworteten Fragen',
    'zeitraumTitel' => 'Zeitraum',
    'ebene_tag' => 'Tag',
    'ebene_monat' => 'Monat',
    'ebene_jahr' => 'Jahr',
    'zurueck' => 'zurück',
    'vor' => 'vor',
    'heute' => 'bis heute',
    'bestand' => '%s veröffentlichte Fragen · %s Mitglieder mit Wertung',
    'keineDaten' => 'Für diesen Zeitraum ist nichts gezählt. Mit „zurück“ lässt sich ein früherer Zeitraum ansteuern; über die Knöpfe oben wird aus dem Tag ein ganzer Monat oder ein ganzes Jahr.',
    'art_gestellt' => 'Fragen gestellt',
    'art_beantwortet' => 'Fragen beantwortet',
    'art_richtig' => 'richtig',
    'art_falsch' => 'falsch',
    'davon' => '%s Mitglieder · %s Gäste',
    'quote' => 'Trefferquote',
    'neueSpieler' => '%s neue Mitglieder im Zeitraum',
    'diagrammGestellt' => 'Gestellte und beantwortete Fragen',
    'diagrammRichtig' => 'Beantwortete und richtig beantwortete Fragen',
    'legendeGestellt' => ['beantwortet', 'gestellt'],
    'legendeRichtig' => ['richtig', 'beantwortet'],
    'themen' => 'Antworten je Thema (Mitglieder)',
    'meistbeantwortet' => 'Meistbeantwortete Fragen (Mitglieder)',
    'aktivste' => 'Aktivste Mitglieder',
    'keineMitglieder' => 'In diesem Zeitraum haben keine Mitglieder geantwortet.',
    'platz' => 'Platz',
    'beantwortetSpalte' => 'Beantwortet',
    'richtigSpalte' => 'Richtig',
    'frage' => 'Frage',
    'thema' => 'Thema',
    'wertung' => 'Wertung',
    'name' => 'Name',
    'aktuelleWertung' => 'Aktuelle Wertung',
    'hinweis' => 'Gestellt zählt jede neu gezogene Frage, nicht die Wiederholung einer offenen Frage nach dem Neuladen. Die Zählung beginnt mit Version 1.4.0. Die Tabellen beruhen auf dem Antwortverlauf der Mitglieder; Gäste werden dort nicht erfasst.',
];
