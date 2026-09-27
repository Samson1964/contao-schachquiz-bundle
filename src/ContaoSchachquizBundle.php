<?php

declare(strict_types=1);

/*
 * Contao Schachquiz Bundle.
 *
 * @license LGPL-3.0-or-later
 */

namespace Schachbulle\ContaoSchachquizBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * Bundle-Klasse des Schachquiz-Bundles.
 *
 * Die Klasse bleibt leer, weil das Bundle weder eigene Compiler-Pässe noch
 * abweichende Verzeichnisse braucht. Symfony leitet Name und Pfad aus dem
 * Klassennamen und dem Dateiort ab; die Klasse muss aber vorhanden sein,
 * damit das Contao-Manager-Plugin sie beim Kernel anmelden kann.
 */
class ContaoSchachquizBundle extends Bundle
{
}
