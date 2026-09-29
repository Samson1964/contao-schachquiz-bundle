<?php

declare(strict_types=1);

/*
 * Contao Schachquiz Bundle.
 *
 * @license LGPL-3.0-or-later
 */

use Schachbulle\ContaoSchachquizBundle\Backend\ImportModul;
use Schachbulle\ContaoSchachquizBundle\Backend\StatistikSeite;

/*
 * Backend-Modul im Bereich „Inhalte" (BE_MOD-Gruppe content), hinter den
 * Modulen des Kerns und der übrigen Erweiterungen.
 *
 * Die Wertungen der Mitglieder (tl_schachquiz_spieler) und die gesicherten
 * Monatsstände (tl_schachquiz_rangliste) sind keine eigenen Module, sondern
 * globale Operationen in der Themenliste; die Tabelle muss
 * dafür hier unter „tables" stehen, sonst verweigert Contao den Zugriff.
 *
 * Die Frontend-Module melden sich über den Dienst-Tag contao.frontend_module
 * in der services.yaml an und stehen deshalb nicht hier.
 */
$GLOBALS['BE_MOD']['content']['schachquiz'] = [
    'tables' => ['tl_schachquiz', 'tl_schachquiz_items', 'tl_schachquiz_spieler', 'tl_schachquiz_rangliste'],
    'import' => [ImportModul::class, 'zeige'],
    // Statistik der gestellten und beantworteten Fragen (do=schachquiz&key=statistik).
    'statistik' => [StatistikSeite::class, 'zeige'],
];
