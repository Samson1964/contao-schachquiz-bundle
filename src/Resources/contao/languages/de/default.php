<?php

declare(strict_types=1);

/*
 * Contao Schachquiz Bundle.
 *
 * @license LGPL-3.0-or-later
 */

// Beschriftungen des Quiz-Skripts; SchachquizController reicht sie als JSON weiter.
$GLOBALS['TL_LANG']['schachquiz']['laden'] = 'Frage wird geladen …';
$GLOBALS['TL_LANG']['schachquiz']['alleThemen'] = 'Alle Themen';
$GLOBALS['TL_LANG']['schachquiz']['thema'] = 'Thema';
$GLOBALS['TL_LANG']['schachquiz']['stufe'] = 'Stufe';
$GLOBALS['TL_LANG']['schachquiz']['einfach'] = 'Eine Antwort ist richtig.';
$GLOBALS['TL_LANG']['schachquiz']['mehrfach'] = 'Eine oder mehrere Antworten sind richtig.';
$GLOBALS['TL_LANG']['schachquiz']['antworten'] = 'Antworten';
$GLOBALS['TL_LANG']['schachquiz']['weiter'] = 'Nächste Frage';
$GLOBALS['TL_LANG']['schachquiz']['richtig'] = 'Richtig!';
$GLOBALS['TL_LANG']['schachquiz']['falsch'] = 'Leider falsch.';
$GLOBALS['TL_LANG']['schachquiz']['wertung'] = 'Wertung';
$GLOBALS['TL_LANG']['schachquiz']['vorlaeufig'] = 'vorläufig';
$GLOBALS['TL_LANG']['schachquiz']['vonRichtig'] = '%d von %d richtig';
$GLOBALS['TL_LANG']['schachquiz']['inSitzung'] = 'diese Sitzung %d von %d';
$GLOBALS['TL_LANG']['schachquiz']['inFolge'] = '%d in Folge richtig';
$GLOBALS['TL_LANG']['schachquiz']['beantwortet'] = 'beantwortet';
$GLOBALS['TL_LANG']['schachquiz']['gast'] = 'Als Gast gilt deine Wertung nur für diesen Besuch. Melde dich an, um in die Rangliste zu kommen.';
$GLOBALS['TL_LANG']['schachquiz']['weissAmZug'] = 'Weiß am Zug';
$GLOBALS['TL_LANG']['schachquiz']['schwarzAmZug'] = 'Schwarz am Zug';
$GLOBALS['TL_LANG']['schachquiz']['fehler'] = 'Das hat nicht geklappt. Bitte lade die Seite neu.';

// Rangliste
$GLOBALS['TL_LANG']['schachquiz']['platz'] = 'Platz';
$GLOBALS['TL_LANG']['schachquiz']['name'] = 'Name';
$GLOBALS['TL_LANG']['schachquiz']['quote'] = 'Quote';
$GLOBALS['TL_LANG']['schachquiz']['besteSerie'] = 'Längste Folge';
$GLOBALS['TL_LANG']['schachquiz']['besteSerieHinweis'] = 'Längste Folge = die meisten richtigen Antworten hintereinander.';
$GLOBALS['TL_LANG']['schachquiz']['keineEintraege'] = 'Noch niemand hat genug Fragen beantwortet.';
$GLOBALS['TL_LANG']['schachquiz']['vorlaeufigHinweis'] = '? = vorläufige Wertung, noch zu wenige Antworten für eine sichere Einschätzung.';
$GLOBALS['TL_LANG']['schachquiz']['mindestensHinweis'] = 'Aufgenommen ab %d beantworteten Fragen.';
$GLOBALS['TL_LANG']['schachquiz']['nochNichtGewertet'] = '(noch %d Antworten bis zur Wertung)';

// Meldungen der Schnittstelle; der Server schickt dazu den Code nach „meldung_“.
$GLOBALS['TL_LANG']['schachquiz']['meldung_anmelden'] = 'Bitte melde dich an, um am Quiz teilzunehmen.';
$GLOBALS['TL_LANG']['schachquiz']['meldung_alleGehabt'] = 'Du hast alle Fragen dieser Auswahl schon gehabt. Wähle ein anderes Thema oder schau später wieder vorbei.';
$GLOBALS['TL_LANG']['schachquiz']['meldung_keineFragen'] = 'Zu dieser Auswahl gibt es noch keine Fragen.';
$GLOBALS['TL_LANG']['schachquiz']['meldung_nichtOffen'] = 'Diese Frage ist nicht mehr offen. Bitte hole eine neue Frage.';
$GLOBALS['TL_LANG']['schachquiz']['meldung_keineAuswahl'] = 'Bitte wähle mindestens eine Antwort.';
$GLOBALS['TL_LANG']['schachquiz']['meldung_nurEine'] = 'Bei dieser Frage ist nur eine Antwort richtig.';
$GLOBALS['TL_LANG']['schachquiz']['meldung_abgewiesen'] = 'Die Anfrage wurde abgewiesen. Bitte lade die Seite neu.';
$GLOBALS['TL_LANG']['schachquiz']['meldung_unbekannt'] = 'Dieses Quiz gibt es nicht mehr.';
$GLOBALS['TL_LANG']['schachquiz']['ohneJs'] = 'Für das Schachquiz wird JavaScript benötigt.';
