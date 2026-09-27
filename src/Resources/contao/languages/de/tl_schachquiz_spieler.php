<?php

declare(strict_types=1);

/*
 * Contao Schachquiz Bundle.
 *
 * @license LGPL-3.0-or-later
 */

$GLOBALS['TL_LANG']['tl_schachquiz_spieler']['member'] = ['Mitglied', ''];
$GLOBALS['TL_LANG']['tl_schachquiz_spieler']['wertung'] = ['Wertung', 'Glicko-2-Wertung in der Elo-Skala.'];
$GLOBALS['TL_LANG']['tl_schachquiz_spieler']['rd'] = ['Abweichung (RD)', 'Unsicherheit der Wertung; über 110 gilt sie als vorläufig.'];
$GLOBALS['TL_LANG']['tl_schachquiz_spieler']['vol'] = ['Volatilität', ''];
$GLOBALS['TL_LANG']['tl_schachquiz_spieler']['anzahl'] = ['Antworten', ''];
$GLOBALS['TL_LANG']['tl_schachquiz_spieler']['richtig'] = ['Davon richtig', ''];
$GLOBALS['TL_LANG']['tl_schachquiz_spieler']['serie'] = ['Aktuelle Serie', ''];
$GLOBALS['TL_LANG']['tl_schachquiz_spieler']['beste_serie'] = ['Beste Serie', ''];
$GLOBALS['TL_LANG']['tl_schachquiz_spieler']['letzte'] = ['Zuletzt gespielt', ''];

$GLOBALS['TL_LANG']['tl_schachquiz_spieler']['delete'] = ['Wertung zurücksetzen', 'Wertung und Verlauf von Eintrag ID %s löschen'];
$GLOBALS['TL_LANG']['tl_schachquiz_spieler']['show'] = ['Details', 'Details von Eintrag ID %s anzeigen'];
$GLOBALS['TL_LANG']['tl_schachquiz_spieler']['loeschen_bestaetigen'] = 'Wertung und Antwortverlauf dieses Mitglieds wirklich löschen? Es beginnt danach wieder bei 1500.';

// Listenbeschriftung (EventListener\DataContainer\SpielerListener)
$GLOBALS['TL_LANG']['tl_schachquiz_spieler']['beschriftung'] = 'Wertung %d%s · %d Antworten · %s richtig · beste Serie %d';
$GLOBALS['TL_LANG']['tl_schachquiz_spieler']['beschriftungBeste'] = 'höchste %d am %s';
$GLOBALS['TL_LANG']['tl_schachquiz_spieler']['beschriftungSeit'] = 'dabei seit %s';
$GLOBALS['TL_LANG']['tl_schachquiz_spieler']['beste_wertung'] = ['Höchste Wertung', 'Höchste gefestigte Wertung (ohne Fragezeichen).'];
$GLOBALS['TL_LANG']['tl_schachquiz_spieler']['beste_datum'] = ['Erreicht am', ''];
$GLOBALS['TL_LANG']['tl_schachquiz_spieler']['erste_nutzung'] = ['Erste Nutzung', ''];
$GLOBALS['TL_LANG']['tl_schachquiz_spieler']['geloescht'] = 'Mitglied %d (gelöscht)';
