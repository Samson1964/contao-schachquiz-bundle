<?php

declare(strict_types=1);

/*
 * Contao Schachquiz Bundle.
 *
 * @license LGPL-3.0-or-later
 */

namespace Schachbulle\ContaoSchachquizBundle\Backend;

use Contao\BackendTemplate;
use Contao\System;
use Schachbulle\ContaoSchachquizBundle\Quiz\Statistik;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Statistik des Quiz (do=schachquiz&key=statistik).
 *
 * Aufbau wie die Statistik des Schachaufgaben-Bundles: Ebenen Tag, Monat und
 * Jahr, Blättern mit „zurück", „vor" und „bis heute", Kennzahlen, Diagramme
 * eine Ebene feiner (Jahr → Monate, Monat → Tage, Tag → Stunden) und
 * Ranglisten als Tabellen.
 *
 * Öffentlicher Dienst, weil Contao den key-Rückruf über
 * System::importStatic() aus dem Container holt.
 */
class StatistikSeite
{
    private const EBENEN = ['tag', 'monat', 'jahr'];

    /** Zeilen der Ranglisten. */
    private const TOP = 20;

    /**
     * Legt die Seite an.
     *
     * @param Statistik    $statistik    Liefert Zähler, Verläufe und Ranglisten
     * @param RequestStack $requestStack Liefert Ebene und Datum aus der Adresse
     */
    public function __construct(
        private readonly Statistik $statistik,
        private readonly RequestStack $requestStack,
    ) {
    }

    /**
     * Baut die Statistikseite.
     *
     * Der Parameter ist untypisiert, weil Contao dem key-Rückruf je nach
     * Fassung ein DataContainer-Objekt oder gar nichts übergibt.
     *
     * @param mixed $dc Der DataContainer von tl_schachquiz; wird nicht gebraucht
     *
     * @return string Das HTML der Seite
     */
    public function zeige($dc = null): string
    {
        System::loadLanguageFile('default');
        System::loadLanguageFile('tl_schachquiz');
        $GLOBALS['TL_CSS'][] = 'bundles/contaoschachquiz/backend.css';

        $request = $this->requestStack->getCurrentRequest();
        $ebene = null === $request ? 'monat' : (string) $request->query->get('ebene', 'monat');
        $ebene = \in_array($ebene, self::EBENEN, true) ? $ebene : 'monat';
        $zeitpunkt = $this->zeitpunkt($request);
        $texte = $GLOBALS['TL_LANG']['tl_schachquiz']['statistik_seite'] ?? [];

        [$von, $bis, $beginn, $ende] = $this->zeitraum($ebene, $zeitpunkt);
        $einheit = ['tag' => 'stunde', 'monat' => 'tag', 'jahr' => 'monat'][$ebene];
        $achse = $this->achse($ebene, $zeitpunkt);

        $summen = $this->statistik->summen($von, $bis);
        $gestellt = $this->statistik->verlauf([Statistik::GESTELLT], $von, $bis, $einheit);
        $beantwortet = $this->statistik->verlauf([Statistik::RICHTIG, Statistik::FALSCH], $von, $bis, $einheit);
        $richtig = $this->statistik->verlauf([Statistik::RICHTIG], $von, $bis, $einheit);

        $balkenGestellt = [];
        $balkenRichtig = [];

        foreach ($achse as $schluessel => $titel) {
            $balkenGestellt[] = ['titel' => $titel, 'wert' => $beantwortet[$schluessel] ?? 0, 'wert2' => $gestellt[$schluessel] ?? 0];
            $balkenRichtig[] = ['titel' => $titel, 'wert' => $richtig[$schluessel] ?? 0, 'wert2' => $beantwortet[$schluessel] ?? 0];
        }

        $vor = $this->verschieben($ebene, $zeitpunkt, 1);
        $antworten = $summen['beantwortet']['gesamt'];

        $template = new BackendTemplate('be_schachquiz_statistik');
        $template->texte = $texte;
        $template->zeitraum = $this->bezeichnung($ebene, $zeitpunkt);
        $template->summen = $summen;
        $template->quote = $antworten > 0 ? (int) round(100 * $summen[Statistik::RICHTIG]['gesamt'] / $antworten) : null;
        $template->bestand = $this->statistik->bestand($beginn, $ende);
        $template->hatDaten = $summen[Statistik::GESTELLT]['gesamt'] + $antworten > 0;
        $template->diagrammGestellt = Diagramm::balken($balkenGestellt, (string) ($texte['diagrammGestellt'] ?? ''), 240, 'monat' === $ebene);
        $template->diagrammRichtig = Diagramm::balken($balkenRichtig, (string) ($texte['diagrammRichtig'] ?? ''), 240, 'monat' === $ebene);
        $template->meistbeantwortet = $this->statistik->meistbeantwortet($beginn, $ende, self::TOP);
        $template->aktivste = $this->statistik->aktivsteMitglieder($beginn, $ende, self::TOP);
        $template->themen = $this->statistik->themen($beginn, $ende);
        $template->ebenenLinks = array_map(
            fn (string $e): array => ['url' => $this->url($request, $e, $zeitpunkt), 'text' => $texte['ebene_'.$e] ?? $e, 'aktiv' => $e === $ebene],
            self::EBENEN
        );
        $template->urlZurueck = $this->url($request, $ebene, $this->verschieben($ebene, $zeitpunkt, -1));
        $template->urlVor = $this->url($request, $ebene, $vor);
        $template->urlHeute = $this->url($request, $ebene, time());
        $template->kannVor = $vor <= time();
        $template->urlModul = htmlspecialchars((null === $request ? '' : $request->getBaseUrl().$request->getPathInfo()).'?do=schachquiz', ENT_QUOTES);

        return $template->parse();
    }

    /**
     * Liest das Datum aus der Adresse; fehlt es, ist es ungültig oder liegt
     * es in der Zukunft, gilt heute.
     *
     * @param Request|null $request Die Anfrage
     *
     * @return int Unix-Zeit, 12 Uhr des gewählten Tags (sicher gegen Zeitumstellungen)
     */
    private function zeitpunkt(?Request $request): int
    {
        $datum = null === $request ? '' : (string) $request->query->get('datum', '');

        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $datum, $teile) && checkdate((int) $teile[2], (int) $teile[3], (int) $teile[1])) {
            $zeit = (int) mktime(12, 0, 0, (int) $teile[2], (int) $teile[3], (int) $teile[1]);

            return min($zeit, (int) mktime(12, 0, 0));
        }

        return (int) mktime(12, 0, 0);
    }

    /**
     * Grenzen des Zeitraums als JJJJMMTT und als Unix-Zeit.
     *
     * @param string $ebene     tag, monat oder jahr
     * @param int    $zeitpunkt Ein Zeitpunkt im Zeitraum
     *
     * @return array{0: int, 1: int, 2: int, 3: int} von und bis (JJJJMMTT, einschließlich),
     *                                               Beginn (einschließlich) und Ende
     *                                               (ausschließlich) als Unix-Zeit
     */
    private function zeitraum(string $ebene, int $zeitpunkt): array
    {
        $j = (int) date('Y', $zeitpunkt);
        $m = (int) date('n', $zeitpunkt);
        $t = (int) date('j', $zeitpunkt);

        return match ($ebene) {
            'tag' => [(int) date('Ymd', $zeitpunkt), (int) date('Ymd', $zeitpunkt), (int) mktime(0, 0, 0, $m, $t, $j), (int) mktime(0, 0, 0, $m, $t + 1, $j)],
            'jahr' => [$j * 10000 + 101, $j * 10000 + 1231, (int) mktime(0, 0, 0, 1, 1, $j), (int) mktime(0, 0, 0, 1, 1, $j + 1)],
            default => [$j * 10000 + $m * 100 + 1, $j * 10000 + $m * 100 + 31, (int) mktime(0, 0, 0, $m, 1, $j), (int) mktime(0, 0, 0, $m + 1, 1, $j)],
        };
    }

    /**
     * Die vollständige Achse des Diagramms, damit leere Zeitpunkte als Lücke erscheinen.
     *
     * @param string $ebene     tag, monat oder jahr
     * @param int    $zeitpunkt Ein Zeitpunkt im Zeitraum
     *
     * @return array<int, string> Stunde, Tag bzw. Monat => Beschriftung
     */
    private function achse(string $ebene, int $zeitpunkt): array
    {
        $achse = [];

        if ('tag' === $ebene) {
            for ($s = 0; $s < 24; ++$s) {
                $achse[$s] = (string) $s;
            }
        } elseif ('jahr' === $ebene) {
            for ($m = 1; $m <= 12; ++$m) {
                $achse[$m] = mb_substr((string) ($GLOBALS['TL_LANG']['MONTHS'][$m - 1] ?? $m), 0, 3);
            }
        } else {
            $tage = (int) date('t', $zeitpunkt);

            for ($t = 1; $t <= $tage; ++$t) {
                $achse[$t] = sprintf('%02d.%s', $t, date('m.', $zeitpunkt));
            }
        }

        return $achse;
    }

    /**
     * Verschiebt den Zeitpunkt um einen Zeitraum der Ebene.
     *
     * @param string $ebene     tag, monat oder jahr
     * @param int    $zeitpunkt Ausgangspunkt
     * @param int    $richtung  -1 zurück, 1 vor
     *
     * @return int Neuer Zeitpunkt, 12 Uhr; beim Monat der Erste, damit es keine Überläufe gibt
     */
    private function verschieben(string $ebene, int $zeitpunkt, int $richtung): int
    {
        $j = (int) date('Y', $zeitpunkt);
        $m = (int) date('n', $zeitpunkt);
        $t = (int) date('j', $zeitpunkt);

        return (int) match ($ebene) {
            'tag' => mktime(12, 0, 0, $m, $t + $richtung, $j),
            'jahr' => mktime(12, 0, 0, 1, 1, $j + $richtung),
            default => mktime(12, 0, 0, $m + $richtung, 1, $j),
        };
    }

    /**
     * Lesbare Bezeichnung des Zeitraums, etwa „29.09.2026", „September 2026" oder „2026".
     *
     * @param string $ebene     tag, monat oder jahr
     * @param int    $zeitpunkt Ein Zeitpunkt im Zeitraum
     *
     * @return string Die Bezeichnung
     */
    private function bezeichnung(string $ebene, int $zeitpunkt): string
    {
        return match ($ebene) {
            'tag' => date('d.m.Y', $zeitpunkt),
            'jahr' => date('Y', $zeitpunkt),
            default => ($GLOBALS['TL_LANG']['MONTHS'][(int) date('n', $zeitpunkt) - 1] ?? date('m', $zeitpunkt)).' '.date('Y', $zeitpunkt),
        };
    }

    /**
     * Adresse der Statistik für eine Ebene und einen Zeitpunkt.
     *
     * @param Request|null $request   Die Anfrage (für Basisadresse und ref)
     * @param string       $ebene     tag, monat oder jahr
     * @param int          $zeitpunkt Ein Zeitpunkt im Zeitraum
     *
     * @return string Maskierte Adresse für das Template
     */
    private function url(?Request $request, string $ebene, int $zeitpunkt): string
    {
        $basis = null === $request ? '' : $request->getBaseUrl().$request->getPathInfo();

        return htmlspecialchars($basis.'?'.http_build_query([
            'do' => 'schachquiz',
            'key' => 'statistik',
            'ebene' => $ebene,
            'datum' => date('Y-m-d', $zeitpunkt),
            'ref' => null === $request ? '' : (string) $request->query->get('ref', ''),
        ]), ENT_QUOTES);
    }
}
