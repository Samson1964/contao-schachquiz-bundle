<?php

declare(strict_types=1);

/*
 * Contao Schachquiz Bundle.
 *
 * @license LGPL-3.0-or-later
 */

use Schachbulle\ContaoSchachquizBundle\Backend\ImportModul;

/*
 * Backend-Modul in einer eigenen Gruppe „Schachquiz".
 *
 * Die Wertungen der Mitglieder (tl_schachquiz_spieler) sind kein eigenes
 * Modul, sondern eine globale Operation in der Themenliste; die Tabelle muss
 * dafür hier unter „tables" stehen, sonst verweigert Contao den Zugriff.
 *
 * Die Frontend-Module melden sich über den Dienst-Tag contao.frontend_module
 * in der services.yaml an und stehen deshalb nicht hier.
 */
$GLOBALS['BE_MOD']['schachquiz'] = [
    'schachquiz' => [
        'tables' => ['tl_schachquiz', 'tl_schachquiz_items', 'tl_schachquiz_spieler'],
        'import' => [ImportModul::class, 'zeige'],
    ],
];
