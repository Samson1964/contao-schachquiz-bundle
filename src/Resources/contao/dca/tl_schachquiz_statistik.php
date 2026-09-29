<?php

declare(strict_types=1);

/*
 * Contao Schachquiz Bundle.
 *
 * @license LGPL-3.0-or-later
 */

use Contao\DC_Table;

/*
 * Tabelle tl_schachquiz_statistik: Zählerstände je Stunde.
 *
 * Eine Zeile je Tag, Stunde, Art und Spielergruppe; jedes Ereignis erhöht nur
 * den Zähler der passenden Zeile (INSERT … ON DUPLICATE KEY UPDATE). Die
 * Tabelle bleibt dadurch klein — höchstens 24 × 3 × 2 Zeilen am Tag — und die
 * Auswertung braucht nur Summen.
 *
 * Arten: gestellt (neue Frage gezogen), richtig, falsch. Keine Backend-Maske;
 * angezeigt wird die Statistik unter Inhalte → Schachquiz → Statistik.
 */
$GLOBALS['TL_DCA']['tl_schachquiz_statistik'] = [
    'config' => [
        'dataContainer' => DC_Table::class,
        'closed' => true,
        'notEditable' => true,
        'notCopyable' => true,
        'notDeletable' => true,
        'sql' => [
            'keys' => [
                'id' => 'primary',
                'datum,stunde,art,gast' => 'unique',
            ],
        ],
    ],

    'fields' => [
        'id' => [
            'sql' => 'int(10) unsigned NOT NULL auto_increment',
        ],
        // Tag als Zahl JJJJMMTT (Serverzeit), etwa 20260929.
        'datum' => [
            'sql' => "int(10) unsigned NOT NULL default '0'",
        ],
        // Stunde 0 bis 23.
        'stunde' => [
            'sql' => "smallint(5) unsigned NOT NULL default '0'",
        ],
        'art' => [
            'sql' => "varchar(16) NOT NULL default ''",
        ],
        // '1' für Gäste, '' für Mitglieder.
        'gast' => [
            'sql' => "char(1) NOT NULL default ''",
        ],
        'anzahl' => [
            'sql' => "int(10) unsigned NOT NULL default '0'",
        ],
    ],
];
