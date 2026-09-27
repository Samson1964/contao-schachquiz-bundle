<?php

declare(strict_types=1);

/*
 * Contao Schachquiz Bundle.
 *
 * @license LGPL-3.0-or-later
 */

namespace Schachbulle\ContaoSchachquizBundle\EventListener\DataContainer;

use Contao\Config;
use Contao\DataContainer;
use Contao\Date;
use Contao\StringUtil;
use Doctrine\DBAL\Connection;
use Schachbulle\ContaoSchachquizBundle\Quiz\Rangliste;
use Schachbulle\ContaoSchachquizBundle\Sprache;
use Schachbulle\ContaoSchachquizBundle\Wertung\Wertungsstand;

/**
 * Rückrufe der Tabelle tl_schachquiz_spieler (Wertungen der Mitglieder).
 */
class SpielerListener
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
     * Beschriftet einen Spieler in der Backend-Liste.
     *
     * @param array<string, mixed> $zeile Die Datenbankzeile
     *
     * @return string Name, Wertung (mit „?" wenn vorläufig), Zahl der
     *                Antworten und Trefferquote
     */
    public function beschriftung(array $zeile): string
    {
        $mitglied = $this->db->fetchAssociative('SELECT firstname, lastname, username FROM tl_member WHERE id = ?', [$zeile['member']]);
        $name = \is_array($mitglied) ? Rangliste::name($mitglied, 'voll') : Sprache::text('tl_schachquiz_spieler', 'geloescht', (int) $zeile['member']);
        $stand = Wertungsstand::ausZeile($zeile);
        $anzahl = (int) $zeile['anzahl'];

        return StringUtil::specialchars($name).' <span style="color:#999;padding-left:3px">['.Sprache::text(
            'tl_schachquiz_spieler',
            'beschriftung',
            (int) round($stand->wertung),
            $stand->istVorlaeufig() ? '?' : '',
            $anzahl,
            $anzahl > 0 ? round(100 * (int) $zeile['richtig'] / $anzahl).' %' : '–',
            (int) $zeile['beste_serie']
        ).$this->zusatz($zeile).']</span>';
    }

    /**
     * Beschriftet einen gesicherten Monatsstand in der Backend-Liste.
     *
     * @param array<string, mixed> $zeile Zeile aus tl_schachquiz_rangliste
     *
     * @return string Monat, Name aus der Sicherung, Wertung und Antworten
     */
    public function monatsstand(array $zeile): string
    {
        $stand = Wertungsstand::ausZeile($zeile);
        $anzahl = (int) $zeile['anzahl'];

        return StringUtil::specialchars($zeile['monat'].' · '.Rangliste::name($zeile, 'voll')).' <span style="color:#999;padding-left:3px">['.Sprache::text(
            'tl_schachquiz_spieler',
            'beschriftung',
            (int) round($stand->wertung),
            $stand->istVorlaeufig() ? '?' : '',
            $anzahl,
            $anzahl > 0 ? round(100 * (int) $zeile['richtig'] / $anzahl).' %' : '–',
            (int) $zeile['beste_serie']
        ).']</span>';
    }

    /**
     * Hängt Höchstwertung und erste Nutzung an die Spielerbeschriftung an.
     *
     * @param array<string, mixed> $zeile Zeile aus tl_schachquiz_spieler
     *
     * @return string Leer, wenn beides fehlt; sonst „ · höchste … · seit …"
     */
    private function zusatz(array $zeile): string
    {
        $format = Config::get('dateFormat');
        $zusatz = '';

        if ((float) ($zeile['beste_wertung'] ?? 0) > 0) {
            $zusatz .= ' · '.Sprache::text('tl_schachquiz_spieler', 'beschriftungBeste', (int) round((float) $zeile['beste_wertung']), Date::parse($format, (int) $zeile['beste_datum']));
        }

        if ((int) ($zeile['erste_nutzung'] ?? 0) > 0) {
            $zusatz .= ' · '.Sprache::text('tl_schachquiz_spieler', 'beschriftungSeit', Date::parse($format, (int) $zeile['erste_nutzung']));
        }

        return $zusatz;
    }

    /**
     * Löscht beim Entfernen eines Spielers auch dessen Antwortverlauf.
     *
     * Ohne den Verlauf bekäme das Mitglied beim Neustart alle Fragen wieder
     * vorgelegt — das ist beim Zurücksetzen gerade gewollt.
     *
     * @param DataContainer $dc Der DataContainer des gelöschten Spielers
     */
    public function loescheVerlauf(DataContainer $dc): void
    {
        $member = $this->db->fetchOne('SELECT member FROM tl_schachquiz_spieler WHERE id = ?', [$dc->id]);

        if (false !== $member) {
            $this->db->delete('tl_schachquiz_verlauf', ['member' => (int) $member]);
        }
    }
}
