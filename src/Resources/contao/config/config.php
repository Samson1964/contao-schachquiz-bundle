<?php

declare(strict_types=1);

/*
 * Contao Schachquiz Bundle.
 *
 * @license LGPL-3.0-or-later
 */

use Schachbulle\ContaoSchachquizBundle\Backend\ImportModul;

/*
 * Backend-Module in einer eigenen Gruppe „Schachquiz".
 *
 * Die Frontend-Module melden sich über den Dienst-Tag contao.frontend_module
 * in der services.yaml an und stehen deshalb nicht hier.
 */
$GLOBALS['BE_MOD']['schachquiz'] = [
    'schachquiz' => [
        'tables' => ['tl_schachquiz', 'tl_schachquiz_items'],
        'import' => [ImportModul::class, 'zeige'],
    ],
    'schachquiz_spieler' => [
        'tables' => ['tl_schachquiz_spieler'],
    ],
];
