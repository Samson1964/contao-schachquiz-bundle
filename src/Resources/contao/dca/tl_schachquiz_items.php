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
 * Tabelle tl_schachquiz_items: die Fragen eines Themas.
 *
 * Jede Frage hat zwei bis sechs Antworten in festen Feldern (antwort1 bis
 * antwort6) und ein Mehrfach-Kontrollkästchen „richtig" mit den Nummern der
 * richtigen Antworten. Feste Felder statt eines Listen-Widgets halten die
 * Maske in Contao 4.13 und 5 gleich und ersparen eine Fremderweiterung.
 *
 * Wertung, Abweichung und Volatilität führt das Glicko-2-System; sie haben
 * keine Eingabefelder. Die redaktionelle Stufe setzt nur den Anfangswert.
 */
$GLOBALS['TL_DCA']['tl_schachquiz_items'] = [
    'config' => [
        'dataContainer' => DC_Table::class,
        'ptable' => 'tl_schachquiz',
        'enableVersioning' => true,
        'sql' => [
            'keys' => [
                'id' => 'primary',
                'pid,published' => 'index',
                'wertung' => 'index',
            ],
        ],
    ],

    'list' => [
        'sorting' => [
            'mode' => DataContainer::MODE_PARENT,
            'fields' => ['schwierigkeit', 'id'],
            'headerFields' => ['title', 'published'],
            'panelLayout' => 'filter;search,limit',
        ],
        'global_operations' => [
            'all' => [
                'href' => 'act=select',
                'class' => 'header_edit_all',
                'attributes' => 'onclick="Backend.getScrollOffset()" accesskey="e"',
            ],
        ],
        'operations' => [
            'edit' => [
                'href' => 'act=edit',
                'icon' => 'edit.svg',
            ],
            'copy' => [
                'href' => 'act=paste&amp;mode=copy',
                'icon' => 'copy.svg',
            ],
            'cut' => [
                'href' => 'act=paste&amp;mode=cut',
                'icon' => 'cut.svg',
            ],
            'delete' => [
                'href' => 'act=delete',
                'icon' => 'delete.svg',
                'attributes' => 'onclick="if(!confirm(\''.($GLOBALS['TL_LANG']['MSC']['deleteConfirm'] ?? '').'\'))return false;Backend.getScrollOffset()"',
            ],
            'toggle' => [
                'href' => 'act=toggle&amp;field=published',
                'icon' => 'visible.svg',
            ],
            'show' => [
                'href' => 'act=show',
                'icon' => 'show.svg',
            ],
        ],
    ],

    'palettes' => [
        'default' => '{frage_legend},frage,typ,schwierigkeit;{stellung_legend},fen,brett;{antworten_legend},antwort1,antwort2,antwort3,antwort4,antwort5,antwort6,richtig;{erklaerung_legend},erklaerung;{publish_legend},published',
    ],

    'fields' => [
        'id' => [
            'sql' => 'int(10) unsigned NOT NULL auto_increment',
        ],
        'pid' => [
            'foreignKey' => 'tl_schachquiz.title',
            'sql' => "int(10) unsigned NOT NULL default '0'",
            'relation' => ['type' => 'belongsTo', 'load' => 'lazy'],
        ],
        'tstamp' => [
            'sql' => "int(10) unsigned NOT NULL default '0'",
        ],
        'frage' => [
            'exclude' => true,
            'search' => true,
            'inputType' => 'textarea',
            'eval' => ['mandatory' => true, 'rows' => 3, 'tl_class' => 'clr'],
            'sql' => 'text NULL',
        ],
        'typ' => [
            'exclude' => true,
            'filter' => true,
            'inputType' => 'select',
            'options' => ['single', 'multiple'],
            'reference' => &$GLOBALS['TL_LANG']['tl_schachquiz_items']['typ_optionen'],
            'eval' => ['tl_class' => 'w50'],
            'sql' => "varchar(16) NOT NULL default 'single'",
        ],
        'schwierigkeit' => [
            'exclude' => true,
            'filter' => true,
            'inputType' => 'select',
            'eval' => ['tl_class' => 'w50'],
            'sql' => "smallint(5) unsigned NOT NULL default '5'",
        ],
        'fen' => [
            'exclude' => true,
            'inputType' => 'text',
            'eval' => ['maxlength' => 100, 'decodeEntities' => true, 'tl_class' => 'w50'],
            'sql' => "varchar(100) NOT NULL default ''",
        ],
        'brett' => [
            'exclude' => true,
            'inputType' => 'select',
            'options' => ['auto', 'weiss', 'schwarz'],
            'reference' => &$GLOBALS['TL_LANG']['tl_schachquiz_items']['brett_optionen'],
            'eval' => ['tl_class' => 'w50'],
            'sql' => "varchar(8) NOT NULL default 'auto'",
        ],
        'antwort1' => [
            'exclude' => true,
            'inputType' => 'text',
            'eval' => ['mandatory' => true, 'maxlength' => 255, 'tl_class' => 'w50'],
            'sql' => "varchar(255) NOT NULL default ''",
        ],
        'antwort2' => [
            'exclude' => true,
            'inputType' => 'text',
            'eval' => ['mandatory' => true, 'maxlength' => 255, 'tl_class' => 'w50'],
            'sql' => "varchar(255) NOT NULL default ''",
        ],
        'antwort3' => [
            'exclude' => true,
            'inputType' => 'text',
            'eval' => ['maxlength' => 255, 'tl_class' => 'w50'],
            'sql' => "varchar(255) NOT NULL default ''",
        ],
        'antwort4' => [
            'exclude' => true,
            'inputType' => 'text',
            'eval' => ['maxlength' => 255, 'tl_class' => 'w50'],
            'sql' => "varchar(255) NOT NULL default ''",
        ],
        'antwort5' => [
            'exclude' => true,
            'inputType' => 'text',
            'eval' => ['maxlength' => 255, 'tl_class' => 'w50'],
            'sql' => "varchar(255) NOT NULL default ''",
        ],
        'antwort6' => [
            'exclude' => true,
            'inputType' => 'text',
            'eval' => ['maxlength' => 255, 'tl_class' => 'w50'],
            'sql' => "varchar(255) NOT NULL default ''",
        ],
        'richtig' => [
            'exclude' => true,
            'inputType' => 'checkbox',
            'eval' => ['mandatory' => true, 'multiple' => true, 'tl_class' => 'clr'],
            'sql' => 'blob NULL',
        ],
        'erklaerung' => [
            'exclude' => true,
            'search' => true,
            'inputType' => 'textarea',
            'eval' => ['rows' => 4, 'tl_class' => 'clr'],
            'sql' => 'text NULL',
        ],
        'published' => [
            'exclude' => true,
            'filter' => true,
            'toggle' => true,
            'inputType' => 'checkbox',
            'eval' => ['doNotCopy' => true],
            'sql' => "char(1) NOT NULL default '1'",
        ],

        // Vom Glicko-2-System geführt, ohne Eingabefeld.
        'wertung' => [
            'sql' => "double NOT NULL default '1500'",
        ],
        'rd' => [
            'sql' => "double NOT NULL default '200'",
        ],
        'vol' => [
            'sql' => "double NOT NULL default '0.06'",
        ],
        'anzahl' => [
            'sql' => "int(10) unsigned NOT NULL default '0'",
        ],
        'anzahl_richtig' => [
            'sql' => "int(10) unsigned NOT NULL default '0'",
        ],
    ],
];
