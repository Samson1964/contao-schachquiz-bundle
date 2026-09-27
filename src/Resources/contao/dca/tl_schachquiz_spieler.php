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
 * Tabelle tl_schachquiz_spieler: die Glicko-2-Wertung je Mitglied.
 *
 * Datensätze entstehen allein durch das Quiz; im Backend lassen sie sich
 * ansehen und löschen. Löschen setzt ein Mitglied zurück — samt Verlauf,
 * siehe SpielerListener::loescheVerlauf().
 */
$GLOBALS['TL_DCA']['tl_schachquiz_spieler'] = [
    'config' => [
        'dataContainer' => DC_Table::class,
        // Aufgerufen als Unteransicht von do=schachquiz; ohne eigene Elterntabelle
        // braucht die Liste den Rückweg ausdrücklich.
        'backlink' => 'do=schachquiz',
        'closed' => true,
        'notEditable' => true,
        'notCopyable' => true,
        'sql' => [
            'keys' => [
                'id' => 'primary',
                'member' => 'unique',
                'wertung' => 'index',
            ],
        ],
    ],

    'list' => [
        'sorting' => [
            'mode' => DataContainer::MODE_SORTED,
            'fields' => ['wertung'],
            'flag' => DataContainer::SORT_DESC,
            // Ohne diese Angabe setzt Contao über jede Zeile eine Gruppenüberschrift
            // mit der nackten Wertung (etwa „1623.4“).
            'disableGrouping' => true,
            'panelLayout' => 'sort,limit',
        ],
        'label' => [
            'fields' => ['member'],
            'format' => '%s',
        ],
        'operations' => [
            'delete' => [
                'href' => 'act=delete',
                'icon' => 'delete.svg',
                'attributes' => 'onclick="if(!confirm(\''.($GLOBALS['TL_LANG']['tl_schachquiz_spieler']['loeschen_bestaetigen'] ?? '').'\'))return false;Backend.getScrollOffset()"',
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
        'tstamp' => [
            'sql' => "int(10) unsigned NOT NULL default '0'",
        ],
        'member' => [
            'foreignKey' => "tl_member.CONCAT(firstname, ' ', lastname)",
            'sql' => "int(10) unsigned NOT NULL default '0'",
            'relation' => ['type' => 'belongsTo', 'load' => 'lazy'],
        ],
        'wertung' => [
            'sorting' => true,
            'sql' => "double NOT NULL default '1500'",
        ],
        'rd' => [
            'sql' => "double NOT NULL default '200'",
        ],
        'vol' => [
            'sql' => "double NOT NULL default '0.06'",
        ],
        'anzahl' => [
            'sorting' => true,
            'sql' => "int(10) unsigned NOT NULL default '0'",
        ],
        'richtig' => [
            'sql' => "int(10) unsigned NOT NULL default '0'",
        ],
        'serie' => [
            'sql' => "int(10) unsigned NOT NULL default '0'",
        ],
        'beste_serie' => [
            'sorting' => true,
            'sql' => "int(10) unsigned NOT NULL default '0'",
        ],
        'letzte' => [
            'sorting' => true,
            'flag' => DataContainer::SORT_DAY_DESC,
            'sql' => "int(10) unsigned NOT NULL default '0'",
        ],
    ],
];
