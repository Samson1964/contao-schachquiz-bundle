<?php

declare(strict_types=1);

/*
 * Contao Schachquiz Bundle.
 *
 * @license LGPL-3.0-or-later
 */

use Contao\DC_Table;

/*
 * Tabelle tl_schachquiz_verlauf: jede Antwort eines Mitglieds.
 *
 * Der Verlauf verhindert, dass ein Mitglied dieselbe Frage erneut bekommt,
 * solange es noch unbeantwortete gibt, und hält die Wertungsentwicklung
 * fest. Er hat keine Backend-Ansicht; die DCA existiert nur, damit Contao
 * die Tabelle anlegt.
 */
$GLOBALS['TL_DCA']['tl_schachquiz_verlauf'] = [
    'config' => [
        'dataContainer' => DC_Table::class,
        'closed' => true,
        'notEditable' => true,
        'notCopyable' => true,
        'notDeletable' => true,
        'sql' => [
            'keys' => [
                'id' => 'primary',
                'member,item' => 'index',
                'item' => 'index',
            ],
        ],
    ],

    'fields' => [
        'id' => [
            'sql' => 'int(10) unsigned NOT NULL auto_increment',
        ],
        'tstamp' => [
            'sql' => "int(10) unsigned NOT NULL default '0'",
        ],
        'member' => [
            'sql' => "int(10) unsigned NOT NULL default '0'",
        ],
        'item' => [
            'sql' => "int(10) unsigned NOT NULL default '0'",
        ],
        'richtig' => [
            'sql' => "char(1) NOT NULL default ''",
        ],
        'antwort' => [
            'sql' => "varchar(64) NOT NULL default ''",
        ],
        'wertung_vorher' => [
            'sql' => "double NOT NULL default '0'",
        ],
        'wertung_nachher' => [
            'sql' => "double NOT NULL default '0'",
        ],
    ],
];
