<?php

declare(strict_types=1);

/*
 * Contao Schachquiz Bundle.
 *
 * @license LGPL-3.0-or-later
 */

namespace Schachbulle\ContaoSchachquizBundle\Quiz;

use Doctrine\DBAL\Connection;
use Schachbulle\ContaoSchachquizBundle\Wertung\Wertungsstand;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * Lädt und speichert Teilnehmer: Mitglieder in der Datenbank, Gäste in der
 * Sitzung.
 */
class TeilnehmerSpeicher
{
    /** Sitzungsschlüssel für den Wertungsstand eines Gastes. */
    public const SITZUNG_GAST = 'schachquiz_gast';

    /**
     * Sitzungsschlüssel für die Fragen, die ein Gast schon gestellt bekam.
     * Bei Mitgliedern steht das im Verlauf.
     */
    public const SITZUNG_GAST_FRAGEN = 'schachquiz_gast_fragen';

    /**
     * Höchstzahl gemerkter Gastfragen. Die Grenze schützt die Sitzung nur vor
     * unbegrenztem Wachstum; erst ein Gast, der in einer Sitzung mehr als
     * 5000 Fragen gesehen hat, bekäme die ältesten wieder vorgelegt.
     */
    private const MAX_GAST_FRAGEN = 5000;

    /**
     * Legt den Speicher an.
     *
     * @param Connection $db Datenbankverbindung von Contao
     */
    public function __construct(private readonly Connection $db)
    {
    }

    /**
     * Lädt den Teilnehmer zur laufenden Anfrage.
     *
     * Ein Mitglied ohne Eintrag in tl_schachquiz_spieler bekommt einen
     * frischen Stand mit Startwertung; angelegt wird der Datensatz erst beim
     * ersten Speichern, damit bloßes Vorbeischauen keine Leichen hinterlässt.
     *
     * @param int              $mitglied ID des angemeldeten Mitglieds, 0 für Gäste
     * @param SessionInterface $sitzung  Die Sitzung, in der Gäste geführt werden
     *
     * @return Teilnehmer Der geladene oder neu angelegte Teilnehmer
     */
    public function lade(int $mitglied, SessionInterface $sitzung): Teilnehmer
    {
        if ($mitglied > 0) {
            $zeile = $this->db->fetchAssociative('SELECT * FROM tl_schachquiz_spieler WHERE member = ?', [$mitglied]);
        } else {
            $zeile = $sitzung->get(self::SITZUNG_GAST);
        }

        if (!\is_array($zeile)) {
            return new Teilnehmer($mitglied);
        }

        return new Teilnehmer(
            $mitglied,
            Wertungsstand::ausZeile($zeile),
            (int) ($zeile['anzahl'] ?? 0),
            (int) ($zeile['richtig'] ?? 0),
            (int) ($zeile['serie'] ?? 0),
            (int) ($zeile['beste_serie'] ?? 0),
        );
    }

    /**
     * Speichert den Teilnehmer.
     *
     * Für Mitglieder wird der Datensatz angelegt oder aktualisiert. Der
     * Eindeutigkeitsschlüssel auf `member` verhindert Doppelungen, falls
     * zwei Anfragen gleichzeitig den ersten Datensatz anlegen wollen.
     *
     * @param Teilnehmer       $teilnehmer Der zu speichernde Stand
     * @param SessionInterface $sitzung    Die Sitzung, in der Gäste geführt werden
     */
    public function speichere(Teilnehmer $teilnehmer, SessionInterface $sitzung): void
    {
        $werte = $teilnehmer->stand->alsZeile() + [
            'anzahl' => $teilnehmer->anzahl,
            'richtig' => $teilnehmer->richtig,
            'serie' => $teilnehmer->serie,
            'beste_serie' => $teilnehmer->besteSerie,
        ];

        if ($teilnehmer->istGast()) {
            $sitzung->set(self::SITZUNG_GAST, $werte);

            return;
        }

        $werte['tstamp'] = time();
        $werte['letzte'] = time();

        $vorhanden = $this->db->fetchOne('SELECT id FROM tl_schachquiz_spieler WHERE member = ?', [$teilnehmer->mitglied]);

        if (false !== $vorhanden) {
            $this->db->update('tl_schachquiz_spieler', $werte, ['id' => $vorhanden]);
        } else {
            $this->db->insert('tl_schachquiz_spieler', $werte + ['member' => $teilnehmer->mitglied]);
        }
    }

    /**
     * Gibt die IDs der Fragen zurück, die ein Gast schon gestellt bekam.
     *
     * @param SessionInterface $sitzung Die Sitzung des Gastes
     *
     * @return list<int> Die Frage-IDs, älteste zuerst
     */
    public function gastFragen(SessionInterface $sitzung): array
    {
        $liste = $sitzung->get(self::SITZUNG_GAST_FRAGEN);

        return \is_array($liste) ? array_values(array_map('intval', $liste)) : [];
    }

    /**
     * Merkt sich, dass ein Gast eine Frage gestellt bekam.
     *
     * @param SessionInterface $sitzung Die Sitzung des Gastes
     * @param int              $frage   ID der Frage
     */
    public function merkeGastFrage(SessionInterface $sitzung, int $frage): void
    {
        $liste = $this->gastFragen($sitzung);
        $liste[] = $frage;

        $sitzung->set(self::SITZUNG_GAST_FRAGEN, \array_slice($liste, -self::MAX_GAST_FRAGEN));
    }
}
