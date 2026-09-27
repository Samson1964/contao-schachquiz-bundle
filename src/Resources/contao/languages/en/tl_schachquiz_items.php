<?php

declare(strict_types=1);

/*
 * Contao Schachquiz Bundle.
 *
 * @license LGPL-3.0-or-later
 */

$GLOBALS['TL_LANG']['tl_schachquiz_items']['frage_legend'] = 'Question';
$GLOBALS['TL_LANG']['tl_schachquiz_items']['stellung_legend'] = 'Position';
$GLOBALS['TL_LANG']['tl_schachquiz_items']['antworten_legend'] = 'Answers';
$GLOBALS['TL_LANG']['tl_schachquiz_items']['erklaerung_legend'] = 'Explanation';
$GLOBALS['TL_LANG']['tl_schachquiz_items']['publish_legend'] = 'Publishing';

$GLOBALS['TL_LANG']['tl_schachquiz_items']['frage'] = ['Question', 'The question text. Line breaks are kept.'];
$GLOBALS['TL_LANG']['tl_schachquiz_items']['typ'] = ['Question type', 'Single choice has exactly one correct answer, multiple choice one or more; then only the completely correct selection counts.'];
$GLOBALS['TL_LANG']['tl_schachquiz_items']['typ_optionen'] = ['single' => 'Single choice', 'multiple' => 'Multiple choice'];
$GLOBALS['TL_LANG']['tl_schachquiz_items']['schwierigkeit'] = ['Difficulty', 'Sets the initial rating of the question. Once members answer, Glicko-2 adjusts the rating by itself.'];
$GLOBALS['TL_LANG']['tl_schachquiz_items']['fen'] = ['Position (FEN)', 'Optional. Shown as a diagram next to the question. Example: r1bqkbnr/pppp1ppp/2n5/4p3/4P3/5N2/PPPP1PPP/RNBQKB1R w KQkq - 2 3'];
$GLOBALS['TL_LANG']['tl_schachquiz_items']['brett'] = ['Board orientation', 'Which side is at the bottom. "Automatic" puts the side to move at the bottom – good for "What is the best move?", less so for "Which opening is this?".'];
$GLOBALS['TL_LANG']['tl_schachquiz_items']['brett_optionen'] = ['auto' => 'Automatic (side to move at the bottom)', 'weiss' => 'White at the bottom', 'schwarz' => 'Black at the bottom'];
$GLOBALS['TL_LANG']['tl_schachquiz_items']['antwort1'] = ['Answer 1', ''];
$GLOBALS['TL_LANG']['tl_schachquiz_items']['antwort2'] = ['Answer 2', ''];
$GLOBALS['TL_LANG']['tl_schachquiz_items']['antwort3'] = ['Answer 3', 'Optional.'];
$GLOBALS['TL_LANG']['tl_schachquiz_items']['antwort4'] = ['Answer 4', 'Optional.'];
$GLOBALS['TL_LANG']['tl_schachquiz_items']['antwort5'] = ['Answer 5', 'Optional.'];
$GLOBALS['TL_LANG']['tl_schachquiz_items']['antwort6'] = ['Answer 6', 'Optional.'];
$GLOBALS['TL_LANG']['tl_schachquiz_items']['richtig'] = ['Correct answer(s)', 'The labels show the answer texts after the first save.'];
$GLOBALS['TL_LANG']['tl_schachquiz_items']['erklaerung'] = ['Explanation', 'Shown after answering, whether right or wrong.'];
$GLOBALS['TL_LANG']['tl_schachquiz_items']['published'] = ['Published', 'Only published questions appear in the quiz.'];

$GLOBALS['TL_LANG']['tl_schachquiz_items']['new'] = ['New question', 'Create a new question'];
$GLOBALS['TL_LANG']['tl_schachquiz_items']['edit'] = ['Edit question', 'Edit question ID %s'];
$GLOBALS['TL_LANG']['tl_schachquiz_items']['copy'] = ['Duplicate question', 'Duplicate question ID %s'];
$GLOBALS['TL_LANG']['tl_schachquiz_items']['cut'] = ['Move question', 'Move question ID %s to another topic'];
$GLOBALS['TL_LANG']['tl_schachquiz_items']['delete'] = ['Delete question', 'Delete question ID %s'];
$GLOBALS['TL_LANG']['tl_schachquiz_items']['toggle'] = ['Publish/unpublish question', 'Publish or unpublish question ID %s'];
$GLOBALS['TL_LANG']['tl_schachquiz_items']['show'] = ['Details', 'Show the details of question ID %s'];

$GLOBALS['TL_LANG']['tl_schachquiz_items']['listenzeile'] = '%s · level %d%s · rating %d · answered %d×, %s correct%s';
$GLOBALS['TL_LANG']['tl_schachquiz_items']['listeEinfach'] = 'Single choice';
$GLOBALS['TL_LANG']['tl_schachquiz_items']['listeMehrfach'] = 'Multiple choice';
$GLOBALS['TL_LANG']['tl_schachquiz_items']['listeTatsaechlich'] = ' (actually %d)';
$GLOBALS['TL_LANG']['tl_schachquiz_items']['listeStellung'] = ' · with position';
$GLOBALS['TL_LANG']['tl_schachquiz_items']['stufenNamen'] = [1 => 'very easy', 3 => 'easy', 5 => 'medium', 7 => 'hard', 10 => 'very hard'];
$GLOBALS['TL_LANG']['tl_schachquiz_items']['stufeOption'] = '%d%s (rating %d)';
$GLOBALS['TL_LANG']['tl_schachquiz_items']['antwortOption'] = 'Answer %d';
$GLOBALS['TL_LANG']['tl_schachquiz_items']['fehlerKeineRichtige'] = 'Please mark at least one correct answer.';
$GLOBALS['TL_LANG']['tl_schachquiz_items']['fehlerLeereRichtige'] = 'Answer %d is marked as correct but empty.';
$GLOBALS['TL_LANG']['tl_schachquiz_items']['fehlerZweiAntworten'] = 'A question needs at least two answers.';
$GLOBALS['TL_LANG']['tl_schachquiz_items']['fehlerNurEine'] = 'A single choice question may have only one correct answer.';
