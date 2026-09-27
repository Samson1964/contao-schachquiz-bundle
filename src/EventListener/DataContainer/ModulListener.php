<?php

declare(strict_types=1);

/*
 * Contao Schachquiz Bundle.
 *
 * @license LGPL-3.0-or-later
 */

namespace Schachbulle\ContaoSchachquizBundle\EventListener\DataContainer;

use Doctrine\DBAL\Connection;
use Schachbulle\ContaoSchachquizBundle\Sprache;

/**
 * Rückrufe für die Quizfelder in tl_module.
 */
class ModulListener
{
    /**
     * Legt den Listener an.
     *
     * @param Connection $db Datenbankverbindung von Contao
     */
    public function __construct(private readonly Connection $db)
    {
    }

    /**
     * Gibt die Themen für die Auswahl im Quizmodul zurück.
     *
     * Auch unveröffentlichte Themen stehen zur Wahl, damit ein Redakteur ein
     * Modul vorbereiten kann, bevor das Thema freigeschaltet wird; das
     * Frontend zeigt ohnehin nur veröffentlichte.
     *
     * @return array<int, string> ID => Titel, unveröffentlichte mit Vermerk
     */
    public function themenOptionen(): array
    {
        $optionen = [];

        foreach ($this->db->fetchAllAssociative('SELECT id, title, published FROM tl_schachquiz ORDER BY title') as $zeile) {
            $optionen[(int) $zeile['id']] = $zeile['title'].('1' === (string) $zeile['published'] ? '' : ' '.Sprache::text('tl_module', 'schachquiz_unveroeffentlicht'));
        }

        return $optionen;
    }
}
