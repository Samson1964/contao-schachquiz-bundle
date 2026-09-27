<?php

declare(strict_types=1);

/*
 * Contao Schachquiz Bundle.
 *
 * @license LGPL-3.0-or-later
 */

namespace Schachbulle\ContaoSchachquizBundle\EventListener\DataContainer;

use Contao\StringUtil;
use Schachbulle\ContaoSchachquizBundle\Sprache;

/**
 * Rückrufe der Tabelle tl_schachquiz (die Quizthemen).
 */
class ThemenListener
{
    /**
     * Beschriftet ein Thema in der Backend-Liste.
     *
     * Im Backend zählt der normale Titel; ein abweichender Titel für das
     * Frontend steht grau dahinter, damit der Redakteur sieht, was Besucher
     * tatsächlich zu lesen bekommen.
     *
     * @param array<string, mixed> $zeile Die Datenbankzeile des Themas
     *
     * @return string HTML der Listenzeile
     */
    public function beschriftung(array $zeile): string
    {
        $titel = StringUtil::specialchars((string) $zeile['title']);
        $frontend = trim((string) ($zeile['titel_frontend'] ?? ''));

        if ('' === $frontend) {
            return $titel;
        }

        return $titel.' <span style="color:#999;padding-left:3px">['.StringUtil::specialchars(Sprache::text('tl_schachquiz', 'listeFrontendtitel', $frontend)).']</span>';
    }
}
