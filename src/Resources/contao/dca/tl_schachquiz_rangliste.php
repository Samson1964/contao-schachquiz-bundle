<?php

declare(strict_types=1);

/*
 * Contao Schachquiz Bundle.
 *
 * @license LGPL-3.0-or-later
 */

use Contao\DataContainer;
use Contao\DC_Table;

/*
 * Tabelle tl_schachquiz_rangliste: die am Monatsersten gesicherten Stände.
 *
 * Je Monat und Mitglied eine Zeile mit den Zahlen zum Stichtag. Die Namen
 * werden mitgesichert, damit ein alter Stand lesbar bleibt, auch wenn ein
 * Mitglied später gelöscht oder umbenannt wird. Einen Rang speichert die
 * Tabelle nicht: Er hängt von der Mindestzahl an Antworten ab, die jedes
 * Ranglistenmodul selbst festlegt, und wird deshalb beim Anzeigen berechnet.
 *
 * Datensätze entstehen allein durch den Cronjob; im Backend lassen sie sich
 * ansehen und löschen.
 */
$GLOBALS['TL_DCA']['tl_schachquiz_rangliste'] = [
    'config' => [
        'dataContainer' => DC_Table::class,
        // Aufgerufen als Unteransicht von do=schachquiz.
        'backlink' => 'do=schachquiz',
        'closed' => true,
        'notEditable' => true,
        'notCopyable' => true,
        'sql' => [
            'keys' => [
                'id' => 'primary',
                'monat,member' => 'unique',
            ],
        ],
    ],

    'list' => [
        'sorting' => [
            'mode' => DataContainer::MODE_SORTED,
            'fields' => ['monat DESC', 'wertung DESC'],
            'flag' => DataContainer::SORT_DESC,
            'panelLayout' => 'filter;limit',
        ],
        'label' => [
            'fields' => ['monat', 'lastname'],
            'format' => '%s',
        ],
        'operations' => [
            'delete' => [
                'href' => 'act=delete',
                'icon' => 'delete.svg',
                'attributes' => 'onclick="if(!confirm(\''.($GLOBALS['TL_LANG']['MSC']['deleteConfirm'] ?? '').'\'))return false;Backend.getScrollOffset()"',
            ],
            'show' => [
                'href' => 'act=show',
                'icon' => 'show.svg',
            ],
        ],
    ],

    'fields' => [
        'id' => [
            'sql' => 'int(10) unsigned NOT NULL auto_increment',
        ],
        // Zeitpunkt der Sicherung.
        'tstamp' => [
            'sql' => "int(10) unsigned NOT NULL default '0'",
        ],
        // Stichtag als „JJJJ-MM“: der Stand zum Ersten dieses Monats.
        'monat' => [
            'filter' => true,
            'sql' => "varchar(7) NOT NULL default ''",
        ],
        'member' => [
            'sql' => "int(10) unsigned NOT NULL default '0'",
        ],
        'firstname' => [
            'sql' => "varchar(255) NOT NULL default ''",
        ],
        'lastname' => [
            'sql' => "varchar(255) NOT NULL default ''",
        ],
        'username' => [
            'sql' => "varchar(64) NOT NULL default ''",
        ],
        'wertung' => [
            'sql' => "double NOT NULL default '0'",
        ],
        'rd' => [
            'sql' => "double NOT NULL default '0'",
        ],
        'anzahl' => [
            'sql' => "int(10) unsigned NOT NULL default '0'",
        ],
        'richtig' => [
            'sql' => "int(10) unsigned NOT NULL default '0'",
        ],
        'beste_serie' => [
            'sql' => "int(10) unsigned NOT NULL default '0'",
        ],
        'beste_wertung' => [
            'sql' => "double NOT NULL default '0'",
        ],
    ],
];
