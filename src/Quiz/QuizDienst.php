<?php

declare(strict_types=1);

/*
 * Contao Schachquiz Bundle.
 *
 * @license LGPL-3.0-or-later
 */

namespace Schachbulle\ContaoSchachquizBundle\Quiz;

use Contao\StringUtil;
use Doctrine\DBAL\Connection;
use Schachbulle\ContaoSchachquizBundle\Import\FragenLeser;
use Schachbulle\ContaoSchachquizBundle\Wertung\Glicko2;
use Schachbulle\ContaoSchachquizBundle\Wertung\Schwierigkeit;
use Schachbulle\ContaoSchachquizBundle\Wertung\Wertungsstand;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * Der Ablauf des Quiz: Frage ziehen, Antwort werten, Wertungen fortschreiben.
 *
 * Die richtigen Antworten verlassen den Server erst mit der Auswertung. Die
 * gerade gestellte Frage steht in der Sitzung, und nur für sie wird eine
 * Antwort angenommen — so lässt sich weder eine beliebige Frage „auf gut
 * Glück" beantworten noch eine unbequeme Frage durch Neuladen der Seite
 * loswerden: Wer die Seite neu lädt, bekommt dieselbe Frage wieder.
 */
class QuizDienst
{
    /** Sitzungsschlüssel für die offenen Fragen, je Modul eine. */
    public const SITZUNG_OFFEN = 'schachquiz_offen';

    /**
     * Themenangabe „wie zuletzt“: Das Skript schickt sie beim Laden der Seite,
     * weil es die zuvor gewählte Lasche nicht kennt. Ohne sie würde ein
     * Neuladen bei gewähltem Thema die offene Frage verfallen lassen.
     */
    public const THEMA_WIE_ZULETZT = -1;

    /**
     * Legt den Dienst an.
     *
     * @param Connection         $db         Datenbankverbindung von Contao
     * @param Fragenauswahl      $auswahl    Sucht die nächste passende Frage
     * @param TeilnehmerSpeicher $speicher   Lädt und speichert Mitglieder und Gäste
     * @param Glicko2            $glicko     Rechnet die neuen Wertungen
     */
    public function __construct(
        private readonly Connection $db,
        private readonly Fragenauswahl $auswahl,
        private readonly TeilnehmerSpeicher $speicher,
        private readonly Glicko2 $glicko,
    ) {
    }

    /**
     * Liefert die aktuelle Frage für einen Teilnehmer.
     *
     * Ist für dieses Modul noch eine Frage offen, kommt sie erneut — außer
     * der Besucher hat inzwischen ein anderes Thema gewählt; dann verfällt
     * die offene Frage ohne Wertung, bleibt aber als gesehen vermerkt und
     * kommt nicht wieder.
     *
     * @param Quizeinstellung  $einstellung Einstellungen des Moduls
     * @param int              $mitglied    ID des angemeldeten Mitglieds, 0 für Gäste
     * @param SessionInterface $sitzung     Die Sitzung des Besuchers
     * @param int              $thema       Vom Besucher gewähltes Thema, 0 für alle,
     *                                      THEMA_WIE_ZULETZT für das Thema der offenen Frage
     *
     * @return array<string, mixed> Antwort für das Skript: `status` ist „ok"
     *                              mit `frage`, `thema` und `spieler`, „leer" wenn es
     *                              keine Fragen gibt, oder „fehler" mit `meldung`
     */
    public function frage(Quizeinstellung $einstellung, int $mitglied, SessionInterface $sitzung, int $thema): array
    {
        if (0 === $mitglied && !$einstellung->gaesteErlaubt) {
            return ['status' => 'fehler', 'code' => 'anmelden', 'meldung' => 'Bitte melde dich an, um am Quiz teilzunehmen.'];
        }

        $teilnehmer = $this->speicher->lade($mitglied, $sitzung);
        $offen = $this->offen($sitzung, $einstellung->modul);

        if (self::THEMA_WIE_ZULETZT === $thema) {
            $thema = $offen['thema'] ?? 0;
        }

        if (!$einstellung->erlaubtThema($thema)) {
            $thema = 0;
        }
        $zeile = null;

        if (null !== $offen && $offen['thema'] === $thema) {
            $zeile = $this->auswahl->lade($offen['frage']);
        }

        if (null === $zeile) {
            $themen = $thema > 0 ? [$thema] : $einstellung->themen;
            $gesehen = $teilnehmer->istGast()
                ? $this->speicher->gastFragen($sitzung)
                : $this->auswahl->gesehenVonMitglied($mitglied);

            $zeile = $this->auswahl->waehle($themen, $teilnehmer, $gesehen);

            if (null === $zeile) {
                $this->setzeOffen($sitzung, $einstellung->modul, null);

                return [
                    'status' => 'leer',
                    'code' => $this->auswahl->anzahl($themen) > 0 ? 'alleGehabt' : 'keineFragen',
                    'meldung' => $this->auswahl->anzahl($themen) > 0
                        ? 'Du hast alle Fragen dieser Auswahl schon gehabt. Wähle ein anderes Thema oder schau später wieder vorbei.'
                        : 'Zu dieser Auswahl gibt es noch keine Fragen.',
                    'thema' => $thema,
                    'spieler' => $teilnehmer->alsAnzeige(),
                ];
            }

            $verlauf = $this->merkeGesehen($teilnehmer, $sitzung, $zeile);
            $this->setzeOffen($sitzung, $einstellung->modul, ['frage' => (int) $zeile['id'], 'thema' => $thema, 'verlauf' => $verlauf]);
        }

        return [
            'status' => 'ok',
            'frage' => $this->frageFuerSkript($zeile),
            'thema' => $thema,
            'spieler' => $teilnehmer->alsAnzeige(),
        ];
    }

    /**
     * Wertet die Antwort auf die offene Frage aus.
     *
     * Bei Mitgliedern werden Spieler und Frage nach Glicko-2 fortgeschrieben
     * und die Antwort im Verlauf festgehalten. Gäste bewegen nur ihre eigene
     * Sitzungswertung; die Wertung der Frage bleibt unberührt, weil anonyme
     * Antworten sonst beliebig oft abgegeben werden könnten, um eine Frage
     * gezielt leicht oder schwer zu machen.
     *
     * @param Quizeinstellung  $einstellung Einstellungen des Moduls
     * @param int              $mitglied    ID des angemeldeten Mitglieds, 0 für Gäste
     * @param SessionInterface $sitzung     Die Sitzung des Besuchers
     * @param list<mixed>      $gewaehlt    Die gewählten Antwortnummern, wie sie
     *                                      aus dem Formular kommen
     *
     * @return array<string, mixed> Antwort für das Skript: `status` „ok" mit
     *                              `richtig`, `korrekt`, `gewaehlt`, `erklaerung`,
     *                              `vorher`, `differenz` und `spieler`, oder
     *                              „fehler" mit `meldung`
     */
    public function antwort(Quizeinstellung $einstellung, int $mitglied, SessionInterface $sitzung, array $gewaehlt): array
    {
        if (0 === $mitglied && !$einstellung->gaesteErlaubt) {
            return ['status' => 'fehler', 'code' => 'anmelden', 'meldung' => 'Bitte melde dich an, um am Quiz teilzunehmen.'];
        }

        $offen = $this->offen($sitzung, $einstellung->modul);
        $zeile = null !== $offen ? $this->auswahl->lade($offen['frage']) : null;

        if (null === $zeile) {
            $this->setzeOffen($sitzung, $einstellung->modul, null);

            return ['status' => 'fehler', 'code' => 'nichtOffen', 'meldung' => 'Diese Frage ist nicht mehr offen. Bitte hole eine neue Frage.'];
        }

        $antworten = $this->antworten($zeile);
        $gewaehlt = array_values(array_unique(array_filter(
            array_map('intval', $gewaehlt),
            static fn (int $nummer): bool => isset($antworten[$nummer])
        )));
        sort($gewaehlt);

        if ([] === $gewaehlt) {
            return ['status' => 'fehler', 'code' => 'keineAuswahl', 'meldung' => 'Bitte wähle mindestens eine Antwort.'];
        }

        if ('single' === $zeile['typ'] && \count($gewaehlt) > 1) {
            return ['status' => 'fehler', 'code' => 'nurEine', 'meldung' => 'Bei dieser Frage ist nur eine Antwort richtig.'];
        }

        $korrekt = $this->korrekt($zeile);

        // Bei Mehrfachauswahl zählt nur die vollständig richtige Auswahl —
        // eine teilweise richtige Antwort ist wie beim Schach ein Fehlzug.
        $richtig = $gewaehlt === $korrekt;

        $teilnehmer = $this->speicher->lade($mitglied, $sitzung);
        $vorher = $teilnehmer->stand;
        $frageStand = Wertungsstand::ausZeile($zeile);

        [$neuSpieler, $neuFrage] = $this->glicko->partie($vorher, $frageStand, $richtig ? 1.0 : 0.0);

        $teilnehmer->verbuche($neuSpieler, $richtig);
        $this->speicher->speichere($teilnehmer, $sitzung);

        // Gäste stehen seit dem Stellen der Frage in ihrer Sitzungsliste;
        // bei Mitgliedern wird der beim Stellen angelegte Verlaufseintrag
        // jetzt um die Antwort ergänzt.
        if (!$teilnehmer->istGast()) {
            // Wurde die Frage noch als Gast gezogen und das Mitglied hat sich
            // erst danach angemeldet, fehlt der Verlaufseintrag noch.
            $verlauf = $offen['verlauf'] ?: $this->merkeGesehen($teilnehmer, $sitzung, $zeile);

            $this->schreibeFrage((int) $zeile['id'], $neuFrage, $richtig);
            $this->db->update('tl_schachquiz_verlauf', [
                'tstamp' => time(),
                'richtig' => $richtig ? '1' : '',
                'antwort' => implode(',', $gewaehlt),
                'wertung_vorher' => $vorher->wertung,
                'wertung_nachher' => $neuSpieler->wertung,
            ], ['id' => $verlauf, 'member' => $mitglied]);
        }

        $this->setzeOffen($sitzung, $einstellung->modul, null);

        return [
            'status' => 'ok',
            'richtig' => $richtig,
            'korrekt' => $korrekt,
            'gewaehlt' => $gewaehlt,
            'erklaerung' => (string) $zeile['erklaerung'],
            'vorher' => (int) round($vorher->wertung),
            'differenz' => (int) round($neuSpieler->wertung) - (int) round($vorher->wertung),
            'spieler' => $teilnehmer->alsAnzeige(),
        ];
    }

    /**
     * Hält fest, dass der Teilnehmer eine Frage gestellt bekommen hat.
     *
     * Das geschieht beim Stellen, nicht erst beim Antworten: So bleibt eine
     * Frage auch dann verbraucht, wenn der Teilnehmer das Thema wechselt,
     * die Sitzung abläuft oder er die Seite schließt. Bei Mitgliedern
     * entsteht ein Verlaufseintrag ohne Antwort (Spalte `antwort` leer), den
     * die Auswertung später ergänzt; bei Gästen ein Eintrag in der Sitzung.
     *
     * @param Teilnehmer           $teilnehmer Der Teilnehmer
     * @param SessionInterface     $sitzung    Die Sitzung des Besuchers
     * @param array<string, mixed> $zeile      Datenbankzeile der gestellten Frage
     *
     * @return int ID des Verlaufseintrags, bei Gästen 0
     */
    private function merkeGesehen(Teilnehmer $teilnehmer, SessionInterface $sitzung, array $zeile): int
    {
        if ($teilnehmer->istGast()) {
            $this->speicher->merkeGastFrage($sitzung, (int) $zeile['id']);

            return 0;
        }

        $this->db->insert('tl_schachquiz_verlauf', [
            'tstamp' => time(),
            'member' => $teilnehmer->mitglied,
            'item' => (int) $zeile['id'],
            'richtig' => '',
            'antwort' => '',
            'wertung_vorher' => $teilnehmer->stand->wertung,
            'wertung_nachher' => $teilnehmer->stand->wertung,
        ]);

        return (int) $this->db->lastInsertId();
    }

    /**
     * Bereitet eine Frage für das Skript auf — ohne die Lösung.
     *
     * @param array<string, mixed> $zeile Datenbankzeile samt `thema_titel`
     *
     * @return array<string, mixed> ID, Text, Typ, FEN, Brettansicht, Antworten mit Nummer,
     *                              Thema und die aktuelle Schwierigkeitsstufe
     */
    private function frageFuerSkript(array $zeile): array
    {
        $antworten = [];

        foreach ($this->antworten($zeile) as $nummer => $text) {
            $antworten[] = ['nr' => $nummer, 'text' => $text];
        }

        return [
            'id' => (int) $zeile['id'],
            'text' => (string) $zeile['frage'],
            'typ' => 'multiple' === $zeile['typ'] ? 'multiple' : 'single',
            'fen' => (string) $zeile['fen'],
            'brett' => \in_array($zeile['brett'] ?? '', ['weiss', 'schwarz'], true) ? $zeile['brett'] : 'auto',
            'antworten' => $antworten,
            'thema' => (string) ($zeile['thema_titel'] ?? ''),
            'stufe' => Schwierigkeit::stufe((float) $zeile['wertung']),
            'wertung' => (int) round((float) $zeile['wertung']),
        ];
    }

    /**
     * Liest die nicht leeren Antworten einer Frage.
     *
     * @param array<string, mixed> $zeile Datenbankzeile der Frage
     *
     * @return array<int, string> Antworttexte nach ihrer Nummer (1 bis 6)
     */
    private function antworten(array $zeile): array
    {
        $antworten = [];

        for ($i = 1; $i <= FragenLeser::MAX_ANTWORTEN; ++$i) {
            $text = trim((string) ($zeile['antwort'.$i] ?? ''));

            if ('' !== $text) {
                $antworten[$i] = $text;
            }
        }

        return $antworten;
    }

    /**
     * Liest die Nummern der richtigen Antworten.
     *
     * @param array<string, mixed> $zeile Datenbankzeile der Frage; `richtig`
     *                                    ist ein serialisiertes Array aus dem
     *                                    Mehrfach-Kontrollkästchen des Backends
     *
     * @return list<int> Die Nummern, aufsteigend sortiert
     */
    private function korrekt(array $zeile): array
    {
        $korrekt = array_map('intval', StringUtil::deserialize($zeile['richtig'] ?? '', true));
        $korrekt = array_values(array_unique($korrekt));
        sort($korrekt);

        return $korrekt;
    }

    /**
     * Schreibt Wertung und Zähler einer Frage fort.
     *
     * Die Zähler werden in SQL hochgezählt statt aus der gelesenen Zeile
     * übernommen, damit gleichzeitige Antworten verschiedener Mitglieder
     * sich nicht gegenseitig überschreiben.
     *
     * @param int           $id      ID der Frage
     * @param Wertungsstand $stand   Neuer Wertungsstand der Frage
     * @param bool          $richtig Ob die Antwort richtig war
     */
    private function schreibeFrage(int $id, Wertungsstand $stand, bool $richtig): void
    {
        $this->db->executeStatement(
            'UPDATE tl_schachquiz_items SET wertung = ?, rd = ?, vol = ?, anzahl = anzahl + 1, anzahl_richtig = anzahl_richtig + ? WHERE id = ?',
            [$stand->wertung, $stand->abweichung, $stand->volatilitaet, $richtig ? 1 : 0, $id]
        );
    }

    /**
     * Liest die offene Frage eines Moduls aus der Sitzung.
     *
     * @param SessionInterface $sitzung Die Sitzung des Besuchers
     * @param int              $modul   ID des Frontendmoduls
     *
     * @return array{frage: int, thema: int, verlauf: int}|null Frage, gewähltes Thema und
     *                                                         ID des Verlaufseintrags (bei
     *                                                         Gästen 0), oder null, wenn
     *                                                         nichts offen ist
     */
    private function offen(SessionInterface $sitzung, int $modul): ?array
    {
        $alle = $sitzung->get(self::SITZUNG_OFFEN);
        $offen = \is_array($alle) ? ($alle[$modul] ?? null) : null;

        if (!\is_array($offen) || !isset($offen['frage'])) {
            return null;
        }

        return ['frage' => (int) $offen['frage'], 'thema' => (int) ($offen['thema'] ?? 0), 'verlauf' => (int) ($offen['verlauf'] ?? 0)];
    }

    /**
     * Setzt oder löscht die offene Frage eines Moduls.
     *
     * @param SessionInterface                   $sitzung Die Sitzung des Besuchers
     * @param int                                $modul   ID des Frontendmoduls
     * @param array{frage: int, thema: int, verlauf: int}|null $offen   Die neue offene Frage,
     *                                                    null zum Löschen
     */
    private function setzeOffen(SessionInterface $sitzung, int $modul, ?array $offen): void
    {
        $alle = $sitzung->get(self::SITZUNG_OFFEN);
        $alle = \is_array($alle) ? $alle : [];

        if (null === $offen) {
            unset($alle[$modul]);
        } else {
            $alle[$modul] = $offen;
        }

        $sitzung->set(self::SITZUNG_OFFEN, $alle);
    }
}
