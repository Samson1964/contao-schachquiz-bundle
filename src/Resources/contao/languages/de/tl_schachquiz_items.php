<?php

declare(strict_types=1);

/*
 * Contao Schachquiz Bundle.
 *
 * @license LGPL-3.0-or-later
 */

$GLOBALS['TL_LANG']['tl_schachquiz_items']['frage_legend'] = 'Frage';
$GLOBALS['TL_LANG']['tl_schachquiz_items']['stellung_legend'] = 'Stellung';
$GLOBALS['TL_LANG']['tl_schachquiz_items']['antworten_legend'] = 'Antworten';
$GLOBALS['TL_LANG']['tl_schachquiz_items']['erklaerung_legend'] = 'Erklärung';
$GLOBALS['TL_LANG']['tl_schachquiz_items']['publish_legend'] = 'Veröffentlichung';

$GLOBALS['TL_LANG']['tl_schachquiz_items']['frage'] = ['Frage', 'Der Fragetext. Zeilenumbrüche bleiben erhalten.'];
$GLOBALS['TL_LANG']['tl_schachquiz_items']['typ'] = ['Fragetyp', 'Bei Einfachauswahl ist genau eine Antwort richtig, bei Mehrfachauswahl eine oder mehrere; dann zählt nur die vollständig richtige Auswahl.'];
$GLOBALS['TL_LANG']['tl_schachquiz_items']['typ_optionen'] = ['single' => 'Einfachauswahl (Single Choice)', 'multiple' => 'Mehrfachauswahl (Multiple Choice)'];
$GLOBALS['TL_LANG']['tl_schachquiz_items']['schwierigkeit'] = ['Schwierigkeit', 'Setzt die Anfangswertung der Frage. Sobald Mitglieder antworten, passt Glicko-2 die Wertung selbst an.'];
$GLOBALS['TL_LANG']['tl_schachquiz_items']['fen'] = ['Stellung (FEN)', 'Optional. Wird als Diagramm neben der Frage gezeigt. Beispiel: r1bqkbnr/pppp1ppp/2n5/4p3/4P3/5N2/PPPP1PPP/RNBQKB1R w KQkq - 2 3'];
$GLOBALS['TL_LANG']['tl_schachquiz_items']['brett'] = ['Brettansicht', 'Welche Seite unten steht. „Automatisch“ stellt die Seite am Zug nach unten – passend für „Was ist der beste Zug?“, weniger für „Welche Eröffnung ist das?“.'];
$GLOBALS['TL_LANG']['tl_schachquiz_items']['brett_optionen'] = ['auto' => 'Automatisch (Seite am Zug unten)', 'weiss' => 'Weiß unten', 'schwarz' => 'Schwarz unten'];
$GLOBALS['TL_LANG']['tl_schachquiz_items']['antwort1'] = ['Antwort 1', ''];
$GLOBALS['TL_LANG']['tl_schachquiz_items']['antwort2'] = ['Antwort 2', ''];
$GLOBALS['TL_LANG']['tl_schachquiz_items']['antwort3'] = ['Antwort 3', 'Optional.'];
$GLOBALS['TL_LANG']['tl_schachquiz_items']['antwort4'] = ['Antwort 4', 'Optional.'];
$GLOBALS['TL_LANG']['tl_schachquiz_items']['antwort5'] = ['Antwort 5', 'Optional.'];
$GLOBALS['TL_LANG']['tl_schachquiz_items']['antwort6'] = ['Antwort 6', 'Optional.'];
$GLOBALS['TL_LANG']['tl_schachquiz_items']['richtig'] = ['Richtige Antwort(en)', 'Die Beschriftung zeigt die Antworttexte erst nach dem ersten Speichern.'];
$GLOBALS['TL_LANG']['tl_schachquiz_items']['erklaerung'] = ['Erklärung', 'Wird nach dem Antworten angezeigt, egal ob richtig oder falsch.'];
$GLOBALS['TL_LANG']['tl_schachquiz_items']['published'] = ['Veröffentlicht', 'Nur veröffentlichte Fragen erscheinen im Quiz.'];

$GLOBALS['TL_LANG']['tl_schachquiz_items']['new'] = ['Neue Frage', 'Eine neue Frage anlegen'];
$GLOBALS['TL_LANG']['tl_schachquiz_items']['edit'] = ['Frage bearbeiten', 'Frage ID %s bearbeiten'];
$GLOBALS['TL_LANG']['tl_schachquiz_items']['copy'] = ['Frage duplizieren', 'Frage ID %s duplizieren'];
$GLOBALS['TL_LANG']['tl_schachquiz_items']['cut'] = ['Frage verschieben', 'Frage ID %s in ein anderes Thema verschieben'];
$GLOBALS['TL_LANG']['tl_schachquiz_items']['delete'] = ['Frage löschen', 'Frage ID %s löschen'];
$GLOBALS['TL_LANG']['tl_schachquiz_items']['toggle'] = ['Frage veröffentlichen/verstecken', 'Frage ID %s veröffentlichen oder verstecken'];
$GLOBALS['TL_LANG']['tl_schachquiz_items']['show'] = ['Details', 'Details der Frage ID %s anzeigen'];

// Listenzeile, Auswahllisten und Prüfmeldungen (EventListener\DataContainer\FragenListener)
$GLOBALS['TL_LANG']['tl_schachquiz_items']['listenzeile'] = '%s · Stufe %d%s · Wertung %d · %d× beantwortet, %s richtig%s';
$GLOBALS['TL_LANG']['tl_schachquiz_items']['listeEinfach'] = 'Einfachauswahl';
$GLOBALS['TL_LANG']['tl_schachquiz_items']['listeMehrfach'] = 'Mehrfachauswahl';
$GLOBALS['TL_LANG']['tl_schachquiz_items']['listeTatsaechlich'] = ' (tatsächlich %d)';
$GLOBALS['TL_LANG']['tl_schachquiz_items']['listeStellung'] = ' · mit Stellung';
$GLOBALS['TL_LANG']['tl_schachquiz_items']['stufenNamen'] = [1 => 'sehr leicht', 3 => 'leicht', 5 => 'mittel', 7 => 'schwer', 10 => 'sehr schwer'];
$GLOBALS['TL_LANG']['tl_schachquiz_items']['stufeOption'] = '%d%s (Wertung %d)';
$GLOBALS['TL_LANG']['tl_schachquiz_items']['antwortOption'] = 'Antwort %d';
$GLOBALS['TL_LANG']['tl_schachquiz_items']['fehlerKeineRichtige'] = 'Bitte mindestens eine richtige Antwort markieren.';
$GLOBALS['TL_LANG']['tl_schachquiz_items']['fehlerLeereRichtige'] = 'Antwort %d ist als richtig markiert, aber leer.';
$GLOBALS['TL_LANG']['tl_schachquiz_items']['fehlerZweiAntworten'] = 'Eine Frage braucht mindestens zwei Antworten.';
$GLOBALS['TL_LANG']['tl_schachquiz_items']['fehlerNurEine'] = 'Bei Einfachauswahl darf nur eine Antwort richtig sein.';
