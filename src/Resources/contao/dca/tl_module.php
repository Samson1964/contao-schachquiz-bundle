<?php

declare(strict_types=1);

/*
 * Contao Schachquiz Bundle.
 *
 * @license LGPL-3.0-or-later
 */

/*
 * Paletten und Felder der beiden Quizmodule in tl_module.
 *
 * `guests` gibt es nur in Contao 4.13; unter Contao 5 fehlt das Feld, und
 * es wird deshalb nur ergänzt, wenn die Kerntabelle es kennt.
 */
$schutz = isset($GLOBALS['TL_DCA']['tl_module']['fields']['guests']) ? 'protected,guests' : 'protected';

$GLOBALS['TL_DCA']['tl_module']['palettes']['schachquiz'] = '{title_legend},name,headline,type;{schachquiz_legend},schachquiz_themen,schachquiz_gaeste,schachquiz_ohneAnimation;{template_legend:hide},customTpl;{protected_legend:hide},'.$schutz.';{expert_legend:hide},cssID';

$GLOBALS['TL_DCA']['tl_module']['palettes']['schachquiz_rangliste'] = '{title_legend},name,headline,type;{schachquiz_legend},schachquiz_ranglistenart,schachquiz_anzahl,schachquiz_mindestzahl,schachquiz_namensformat;{template_legend:hide},customTpl;{protected_legend:hide},'.$schutz.';{expert_legend:hide},cssID';

unset($schutz);

$GLOBALS['TL_DCA']['tl_module']['fields']['schachquiz_themen'] = [
    'exclude' => true,
    'inputType' => 'checkbox',
    'eval' => ['multiple' => true, 'tl_class' => 'clr'],
    'sql' => 'blob NULL',
];

$GLOBALS['TL_DCA']['tl_module']['fields']['schachquiz_gaeste'] = [
    'exclude' => true,
    'inputType' => 'checkbox',
    'eval' => ['tl_class' => 'w50 m12'],
    'sql' => "char(1) NOT NULL default '1'",
];

$GLOBALS['TL_DCA']['tl_module']['fields']['schachquiz_ohneAnimation'] = [
    'exclude' => true,
    'inputType' => 'checkbox',
    'eval' => ['tl_class' => 'w50 m12'],
    'sql' => "char(1) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_module']['fields']['schachquiz_ranglistenart'] = [
    'exclude' => true,
    'inputType' => 'select',
    'options' => ['aktuell', 'ewig', 'monat'],
    'reference' => &$GLOBALS['TL_LANG']['tl_module']['schachquiz_ranglistenart_optionen'],
    'eval' => ['tl_class' => 'w50'],
    'sql' => "varchar(16) NOT NULL default 'aktuell'",
];

$GLOBALS['TL_DCA']['tl_module']['fields']['schachquiz_anzahl'] = [
    'exclude' => true,
    'inputType' => 'text',
    'eval' => ['rgxp' => 'natural', 'minval' => 1, 'tl_class' => 'w50'],
    'sql' => "smallint(5) unsigned NOT NULL default '20'",
];

$GLOBALS['TL_DCA']['tl_module']['fields']['schachquiz_mindestzahl'] = [
    'exclude' => true,
    'inputType' => 'text',
    'eval' => ['rgxp' => 'natural', 'tl_class' => 'w50'],
    'sql' => "smallint(5) unsigned NOT NULL default '10'",
];

$GLOBALS['TL_DCA']['tl_module']['fields']['schachquiz_namensformat'] = [
    'exclude' => true,
    'inputType' => 'select',
    'options' => ['kurz', 'voll', 'benutzer'],
    'reference' => &$GLOBALS['TL_LANG']['tl_module']['schachquiz_namensformat_optionen'],
    'eval' => ['tl_class' => 'w50'],
    'sql' => "varchar(16) NOT NULL default 'kurz'",
];
