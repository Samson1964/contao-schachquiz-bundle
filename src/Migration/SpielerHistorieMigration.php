<?php

declare(strict_types=1);

/*
 * Contao Schachquiz Bundle.
 *
 * @license LGPL-3.0-or-later
 */

namespace Schachbulle\ContaoSchachquizBundle\Migration;

use Contao\CoreBundle\Migration\AbstractMigration;
use Contao\CoreBundle\Migration\MigrationResult;
use Doctrine\DBAL\Connection;
use Schachbulle\ContaoSchachquizBundle\Wertung\Wertungsstand;

/**
 * Trägt bei Spielern aus der Zeit vor Version 1.3.0 die neuen Angaben nach.
 *
 * - Erste Nutzung: die früheste Antwort im Verlauf, ersatzweise der
 *   Zeitstempel des Spielerdatensatzes.
 * - Höchste Wertung: die aktuelle Wertung, sofern sie gefestigt ist. Einen
 *   früheren, höheren Stand kann niemand rekonstruieren, weil der Verlauf
 *   die Abweichung nicht speichert und damit nicht erkennbar ist, ob ein
 *   Zwischenstand schon gefestigt war.
 *
 * Die Migration läuft erst, wenn die neuen Spalten existieren. Contao führt
 * contao:migrate so lange erneut aus, bis nichts mehr zu tun ist; nach dem
 * Anlegen der Spalten kommt sie also im selben Durchgang an die Reihe.
 */
class SpielerHistorieMigration extends AbstractMigration
{
    /**
     * Legt die Migration an.
     *
     * @param Connection $db Datenbankverbindung von Contao
     */
    public function __construct(private readonly Connection $db)
    {
    }

    /**
     * Beschreibt die Migration für die Ausgabe von contao:migrate.
     *
     * @return string Kurzbeschreibung
     */
    public function getName(): string
    {
        return 'Schachquiz: erste Nutzung und höchste Wertung nachtragen';
    }

    /**
     * Sagt, ob es Spieler ohne erste Nutzung gibt.
     *
     * @return bool true, wenn Tabelle und Spalten existieren und mindestens
     *              ein Spieler mit Antworten noch keine erste Nutzung hat
     */
    public function shouldRun(): bool
    {
        $schema = $this->db->createSchemaManager();

        if (!$schema->tablesExist(['tl_schachquiz_spieler', 'tl_schachquiz_verlauf'])) {
            return false;
        }

        $spalten = $schema->listTableColumns('tl_schachquiz_spieler');

        if (!isset($spalten['erste_nutzung'], $spalten['beste_wertung'], $spalten['beste_datum'])) {
            return false;
        }

        return (int) $this->db->fetchOne('SELECT COUNT(*) FROM tl_schachquiz_spieler WHERE erste_nutzung = 0 AND anzahl > 0') > 0;
    }

    /**
     * Trägt erste Nutzung und höchste Wertung nach.
     *
     * @return MigrationResult Ergebnis mit der Zahl der ergänzten Spieler
     */
    public function run(): MigrationResult
    {
        $ergaenzt = $this->db->executeStatement(
            "UPDATE tl_schachquiz_spieler s
                SET s.erste_nutzung = COALESCE(
                    (SELECT MIN(v.tstamp) FROM tl_schachquiz_verlauf v WHERE v.member = s.member AND v.tstamp > 0),
                    NULLIF(s.tstamp, 0),
                    UNIX_TIMESTAMP()
                )
                WHERE s.erste_nutzung = 0 AND s.anzahl > 0"
        );

        $this->db->executeStatement(
            'UPDATE tl_schachquiz_spieler
                SET beste_wertung = wertung, beste_datum = IF(letzte > 0, letzte, tstamp)
                WHERE beste_wertung = 0 AND anzahl > 0 AND rd <= ?',
            [Wertungsstand::VORLAEUFIG_AB]
        );

        return $this->createResult(true, sprintf('Bei %d Spielern erste Nutzung und höchste Wertung nachgetragen.', $ergaenzt));
    }
}
