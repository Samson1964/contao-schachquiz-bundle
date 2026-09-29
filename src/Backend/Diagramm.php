<?php

declare(strict_types=1);

/*
 * Contao Schachquiz Bundle.
 *
 * @license LGPL-3.0-or-later
 */

namespace Schachbulle\ContaoSchachquizBundle\Backend;

/**
 * Balkendiagramm als SVG, serverseitig erzeugt, ohne JavaScript.
 *
 * Aufbau, Maße und Farben wie im Diagramm der Schachaufgaben- und
 * Counter-Statistik, damit die Statistiken der schachbulle-Bundles im
 * Backend gleich aussehen. Eine zweite Balkenreihe steht hell hinter der
 * ersten — so zeigt ein Diagramm etwa gestellte und beantwortete Fragen zugleich.
 */
final class Diagramm
{
    public const FARBE = '#1F618D';

    public const FARBE_HELL = '#7FB3D5';

    /**
     * Zeichnet ein Balkendiagramm.
     *
     * Die Balkenbreite richtet sich nach der Zahl der Balken (8 bis 48 Pixel):
     * Zwölf Monate bekommen breite Balken, 31 Tage schmalere.
     *
     * @param list<array{titel: string|int, wert: int, wert2?: int}> $balken       Beschriftung und Wert je
     *                                                                           Balken; „wert2" ist die
     *                                                                           optionale hintere Reihe
     * @param string                                                 $beschriftung Kurztext für die
     *                                                                           Vorlesehilfe (aria-label)
     * @param int                                                    $hoehe        Gesamthöhe in Pixeln
     * @param bool                                                   $schraeg      Beschriftung um 45 Grad
     *                                                                           drehen, nötig bei langen Texten
     *
     * @return string Fertiges, maskiertes SVG, oder '' bei leerer Liste
     */
    public static function balken(array $balken, string $beschriftung, int $hoehe = 220, bool $schraeg = false): string
    {
        if ([] === $balken) {
            return '';
        }

        $randLinks = 48;
        $randUnten = $schraeg ? 48 : 26;
        $randOben = 16;
        $abstand = 6;
        $zielBreite = 900;
        $balkenBreite = (int) max(8, min(48, round(($zielBreite - $randLinks - 20) / \count($balken) - $abstand)));
        $breite = $randLinks + \count($balken) * ($balkenBreite + $abstand) + 20;
        $nutzHoehe = $hoehe - $randOben - $randUnten;

        $max = 0;

        foreach ($balken as $b) {
            $max = max($max, (int) $b['wert'], (int) ($b['wert2'] ?? 0));
        }

        $maxSkala = self::skala($max);

        // display:block, weil ein SVG sonst als Inline-Element neben schwebende Inhalte rutscht.
        $svg = [];
        $svg[] = '<svg viewBox="0 0 '.$breite.' '.$hoehe.'" width="100%" height="'.$hoehe.'" role="img" aria-label="'
            .htmlspecialchars($beschriftung, ENT_QUOTES).'" xmlns="http://www.w3.org/2000/svg"'
            .' style="display:block;max-width:'.$breite.'px;min-width:'.min($breite, 560).'px">';
        $svg[] = '<style>.sq-achse{font:11px sans-serif;fill:#666}.sq-wert{font:10px sans-serif;fill:#333}</style>';

        for ($i = 0; $i <= 4; ++$i) {
            $wert = $maxSkala / 4 * $i;
            $y = $randOben + $nutzHoehe - ($nutzHoehe / 4 * $i);
            $svg[] = '<line x1="'.$randLinks.'" y1="'.round($y, 1).'" x2="'.($breite - 10).'" y2="'.round($y, 1).'" stroke="#e2e2e2" stroke-width="1"/>';
            $svg[] = '<text x="'.($randLinks - 8).'" y="'.round($y + 4, 1).'" text-anchor="end" class="sq-achse">'.round($wert).'</text>';
        }

        $x = $randLinks + $abstand / 2;
        $werteZeigen = $balkenBreite >= 16;

        foreach ($balken as $b) {
            $wert = (int) $b['wert'];
            $titel = htmlspecialchars((string) $b['titel'], ENT_QUOTES);

            // Hintere, helle Reihe in voller Breite, die vordere schmaler darüber.
            if (isset($b['wert2']) && (int) $b['wert2'] > 0) {
                $h2 = round($nutzHoehe * (int) $b['wert2'] / $maxSkala, 1);
                $svg[] = '<rect x="'.$x.'" y="'.($randOben + $nutzHoehe - $h2).'" width="'.$balkenBreite.'" height="'.$h2.'" fill="'.self::FARBE_HELL
                    .'"><title>'.$titel.': '.(int) $b['wert2'].'</title></rect>';
            }

            $h = round($nutzHoehe * $wert / $maxSkala, 1);
            $y = $randOben + $nutzHoehe - $h;
            $innen = isset($b['wert2']) ? max(4, (int) round($balkenBreite * 0.6)) : $balkenBreite;
            $xi = $x + ($balkenBreite - $innen) / 2;

            if ($h > 0) {
                $svg[] = '<rect x="'.$xi.'" y="'.$y.'" width="'.$innen.'" height="'.$h.'" fill="'.self::FARBE
                    .'"><title>'.$titel.': '.$wert.'</title></rect>';
            }

            $oben = isset($b['wert2']) ? max($wert, (int) $b['wert2']) : $wert;

            if ($werteZeigen && $oben > 0) {
                $yWert = $randOben + $nutzHoehe - round($nutzHoehe * $oben / $maxSkala, 1) - 4;
                $svg[] = '<text x="'.($x + $balkenBreite / 2).'" y="'.round($yWert, 1).'" text-anchor="middle" class="sq-wert">'.$oben.'</text>';
            }

            $xt = $x + $balkenBreite / 2;
            $yt = $randOben + $nutzHoehe + 14;

            $svg[] = $schraeg
                ? '<text x="'.$xt.'" y="'.$yt.'" text-anchor="end" class="sq-achse" transform="rotate(-45 '.$xt.' '.$yt.')">'.$titel.'</text>'
                : '<text x="'.$xt.'" y="'.$yt.'" text-anchor="middle" class="sq-achse">'.$titel.'</text>';

            $x += $balkenBreite + $abstand;
        }

        $svg[] = '<line x1="'.$randLinks.'" y1="'.($randOben + $nutzHoehe).'" x2="'.($breite - 10).'" y2="'.($randOben + $nutzHoehe).'" stroke="#999" stroke-width="1"/>';
        $svg[] = '</svg>';

        return implode("\n", $svg);
    }

    /**
     * Rundet den Höchstwert auf eine gut teilbare Skalenobergrenze auf.
     *
     * Beispiele: 7 wird zu 8, 43 zu 44, 512 zu 520.
     *
     * @param int $max Größter vorkommender Wert; 0 ist erlaubt
     *
     * @return int Obergrenze der Skala, mindestens 4 und durch 4 teilbar
     */
    public static function skala(int $max): int
    {
        if ($max < 4) {
            return 4;
        }

        $schritt = max(1, (int) 10 ** max(0, \strlen((string) $max) - 2));

        return (int) (ceil($max / ($schritt * 4)) * $schritt * 4);
    }
}
