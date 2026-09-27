<?php

declare(strict_types=1);

/*
 * Contao Schachquiz Bundle.
 *
 * @license LGPL-3.0-or-later
 */

$GLOBALS['TL_LANG']['tl_schachquiz']['title_legend'] = 'Topic';
$GLOBALS['TL_LANG']['tl_schachquiz']['publish_legend'] = 'Publishing';

$GLOBALS['TL_LANG']['tl_schachquiz']['title'] = ['Title', 'Name of the topic, e.g. "Rules" or "Openings". It is shown as a choice in the front end.'];
$GLOBALS['TL_LANG']['tl_schachquiz']['titel_frontend'] = ['Title in the front end', 'Optional. Shown to visitors instead of the title; the back end keeps using the title.'];
$GLOBALS['TL_LANG']['tl_schachquiz']['listeFrontendtitel'] = 'front end: %s';
$GLOBALS['TL_LANG']['tl_schachquiz']['beschreibung'] = ['Description', 'Optional description for editors.'];
$GLOBALS['TL_LANG']['tl_schachquiz']['published'] = ['Published', 'Only questions of published topics appear in the quiz.'];

$GLOBALS['TL_LANG']['tl_schachquiz']['new'] = ['New topic', 'Create a new quiz topic'];
$GLOBALS['TL_LANG']['tl_schachquiz']['wertungen'] = ['Quiz ratings', 'View and reset the Glicko-2 ratings of members'];
$GLOBALS['TL_LANG']['tl_schachquiz']['import'] = ['Import questions', 'Import questions from a CSV or JSON file'];
$GLOBALS['TL_LANG']['tl_schachquiz']['edit'] = ['Edit questions', 'Edit the questions of topic ID %s'];
$GLOBALS['TL_LANG']['tl_schachquiz']['editheader'] = ['Edit topic', 'Edit the settings of topic ID %s'];
$GLOBALS['TL_LANG']['tl_schachquiz']['copy'] = ['Duplicate topic', 'Duplicate topic ID %s'];
$GLOBALS['TL_LANG']['tl_schachquiz']['delete'] = ['Delete topic', 'Delete topic ID %s including its questions'];
$GLOBALS['TL_LANG']['tl_schachquiz']['toggle'] = ['Publish/unpublish topic', 'Publish or unpublish topic ID %s'];
$GLOBALS['TL_LANG']['tl_schachquiz']['show'] = ['Details', 'Show the details of topic ID %s'];

$GLOBALS['TL_LANG']['tl_schachquiz']['import_datei'] = 'File (CSV or JSON)';
$GLOBALS['TL_LANG']['tl_schachquiz']['import_dateiHilfe'] = 'CSV with a header row (semicolon, comma or tab; UTF-8 or Excel encoding) or JSON.';
$GLOBALS['TL_LANG']['tl_schachquiz']['import_ziel'] = 'Target topic';
$GLOBALS['TL_LANG']['tl_schachquiz']['import_zielHilfe'] = 'Without a target topic, the topics from the column "thema" or the JSON key "themen" are created or extended.';
$GLOBALS['TL_LANG']['tl_schachquiz']['import_ausDatei'] = 'Take topics from the file';
$GLOBALS['TL_LANG']['tl_schachquiz']['import_vorlagen'] = 'Templates';
$GLOBALS['TL_LANG']['tl_schachquiz']['import_vorlageCsv'] = 'Download CSV template';
$GLOBALS['TL_LANG']['tl_schachquiz']['import_vorlageJson'] = 'Download JSON template';
$GLOBALS['TL_LANG']['tl_schachquiz']['import_spalten'] = 'CSV columns: thema, beschreibung, frage, typ (single/multiple), schwierigkeit (1–10), fen, brett (auto/weiss/schwarz), antwort1 … antwort6, richtig (e.g. "2" or "1,3"), erklaerung. Questions with identical text and position in the same topic are skipped, so the same file can safely be imported again.';
$GLOBALS['TL_LANG']['tl_schachquiz']['import_absenden'] = 'Import';
$GLOBALS['TL_LANG']['tl_schachquiz']['import_beispiel'] = 'Import the bundled sample questions';
$GLOBALS['TL_LANG']['tl_schachquiz']['import_keineDatei'] = 'Please choose a CSV or JSON file.';
$GLOBALS['TL_LANG']['tl_schachquiz']['import_nichtsGueltig'] = 'The file contained no valid question. Nothing was imported.';
$GLOBALS['TL_LANG']['tl_schachquiz']['import_abgebrochen'] = 'The import was aborted, nothing was saved: %s';
$GLOBALS['TL_LANG']['tl_schachquiz']['import_erfolg'] = '%d questions imported';
$GLOBALS['TL_LANG']['tl_schachquiz']['import_neueThemen'] = ', %d new topics created';
$GLOBALS['TL_LANG']['tl_schachquiz']['import_uebersprungen'] = ', %d existing questions skipped';
$GLOBALS['TL_LANG']['tl_schachquiz']['import_weitereFehler'] = '… and %d more errors.';
