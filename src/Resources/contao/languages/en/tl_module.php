<?php

declare(strict_types=1);

/*
 * Contao Schachquiz Bundle.
 *
 * @license LGPL-3.0-or-later
 */

$GLOBALS['TL_LANG']['tl_module']['schachquiz_legend'] = 'Chess quiz';

$GLOBALS['TL_LANG']['tl_module']['schachquiz_themen'] = ['Topics', 'Topics the questions come from. Without a selection, all published topics are used.'];
$GLOBALS['TL_LANG']['tl_module']['schachquiz_gaeste'] = ['Allow guests', 'Visitors who are not logged in play with a rating that only lasts for their session and does not enter the ranking.'];
$GLOBALS['TL_LANG']['tl_module']['schachquiz_ohneAnimation'] = ['Disable piece animations', 'No chess pieces that think along, cheer or mourn. Visitors with "reduce motion" enabled in their system never see them anyway.'];
$GLOBALS['TL_LANG']['tl_module']['schachquiz_ranglistenart'] = ['Ranking type', 'Current rating, all-time best list (highest rating ever reached) or a standing saved on the first of a month, selectable by visitors.'];
$GLOBALS['TL_LANG']['tl_module']['schachquiz_ranglistenart_optionen'] = ['aktuell' => 'Current rating', 'ewig' => 'All-time best', 'monat' => 'Monthly standings'];
$GLOBALS['TL_LANG']['tl_module']['schachquiz_anzahl'] = ['Number of places', 'How many places the ranking shows.'];
$GLOBALS['TL_LANG']['tl_module']['schachquiz_mindestzahl'] = ['Minimum answers', 'A member appears in the ranking only after answering this many questions.'];
$GLOBALS['TL_LANG']['tl_module']['schachquiz_namensformat'] = ['Name format', 'How members are named in the public ranking.'];
$GLOBALS['TL_LANG']['tl_module']['schachquiz_namensformat_optionen'] = [
    'kurz' => 'First name and initial (Anna S.)',
    'voll' => 'First and last name (Anna Smith)',
    'benutzer' => 'Username',
];
$GLOBALS['TL_LANG']['tl_module']['schachquiz_unveroeffentlicht'] = '(unpublished)';
$GLOBALS['TL_LANG']['tl_module']['schachquiz_frontendtitel'] = '(front end: %s)';
