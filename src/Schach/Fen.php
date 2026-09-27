<?php

declare(strict_types=1);

/*
 * Contao Schachquiz Bundle.
 *
 * @license LGPL-3.0-or-later
 */

namespace Schachbulle\ContaoSchachquizBundle\Schach;

/**
 * Prüft und zerlegt Stellungen in Forsyth-Edwards-Notation (FEN).
 *
 * Das Brett selbst zeichnet das Frontend-Skript; hier geht es darum, eine
 * fehlerhafte Stellung schon beim Speichern oder Importieren abzuweisen,
 * damit im Quiz kein kaputtes Diagramm erscheint.
 *
 * Akzeptiert wird auch die verkürzte Form, die nur aus der Figurenstellung
 * besteht (etwa aus einem Diagrammeditor kopiert). Fehlende Felder werden
 * mit „w - - 0 1" ergänzt.
 */
final class Fen
{
    /**
     * Prüft eine FEN und gibt sie vervollständigt zurück.
     *
     * Geprüft werden: acht Reihen mit je acht Feldern, nur gültige
     * Figurenbuchstaben, genau ein König je Farbe, keine Bauern auf der
     * ersten oder achten Reihe und ein gültiger Zugrechtvermerk. Ob die
     * Stellung aus einer echten Partie stammen könnte (etwa mit neun Damen),
     * wird bewusst nicht geprüft — Studien und Lehrbeispiele dürfen das.
     *
     * @param string $fen Die Stellung; führende und folgende Leerzeichen
     *                    sind erlaubt
     *
     * @throws \InvalidArgumentException Mit einer deutschen Fehlermeldung,
     *                                   die sich direkt im Backend anzeigen lässt
     *
     * @return string Die vollständige FEN mit sechs Feldern
     */
    public static function pruefe(string $fen): string
    {
        $teile = preg_split('/\s+/', trim($fen)) ?: [];

        if ([] === $teile || '' === $teile[0]) {
            throw new \InvalidArgumentException('Die Stellung ist leer.');
        }

        if (\count($teile) > 6) {
            throw new \InvalidArgumentException('Die FEN hat mehr als sechs Felder.');
        }

        $brett = self::brett($teile[0]);

        $zugrecht = $teile[1] ?? 'w';

        if ('w' !== $zugrecht && 'b' !== $zugrecht) {
            throw new \InvalidArgumentException(sprintf('Das Zugrecht muss „w" oder „b" sein, nicht „%s".', $zugrecht));
        }

        $rochade = $teile[2] ?? '-';

        if (!preg_match('/^(-|K?Q?k?q?)$/', $rochade) || '' === $rochade) {
            throw new \InvalidArgumentException(sprintf('Ungültiges Rochaderecht „%s".', $rochade));
        }

        $enPassant = $teile[3] ?? '-';

        if (!preg_match('/^(-|[a-h][36])$/', $enPassant)) {
            throw new \InvalidArgumentException(sprintf('Ungültiges En-passant-Feld „%s".', $enPassant));
        }

        $halbzuege = $teile[4] ?? '0';
        $zugnummer = $teile[5] ?? '1';

        if (!ctype_digit($halbzuege) || !ctype_digit($zugnummer) || (int) $zugnummer < 1) {
            throw new \InvalidArgumentException('Halbzugzähler und Zugnummer müssen Zahlen sein, die Zugnummer mindestens 1.');
        }

        $koenige = ['K' => 0, 'k' => 0];

        foreach ($brett as $r => $reihe) {
            foreach ($reihe as $figur) {
                if (isset($koenige[$figur])) {
                    ++$koenige[$figur];
                }

                // $r = 0 ist die achte Reihe, $r = 7 die erste.
                if (('P' === $figur || 'p' === $figur) && (0 === $r || 7 === $r)) {
                    throw new \InvalidArgumentException('Bauern dürfen nicht auf der ersten oder achten Reihe stehen.');
                }
            }
        }

        if (1 !== $koenige['K'] || 1 !== $koenige['k']) {
            throw new \InvalidArgumentException(sprintf('Es muss genau ein weißer und ein schwarzer König auf dem Brett stehen (gefunden: %d weiß, %d schwarz).', $koenige['K'], $koenige['k']));
        }

        return implode(' ', [$teile[0], $zugrecht, $rochade, $enPassant, $halbzuege, $zugnummer]);
    }

    /**
     * Sagt, ob in der Stellung Schwarz am Zug ist.
     *
     * Das Frontend dreht das Brett dann, damit die Seite am Zug unten steht.
     *
     * @param string $fen Eine bereits geprüfte FEN
     *
     * @return bool true bei Zugrecht Schwarz
     */
    public static function schwarzAmZug(string $fen): bool
    {
        $teile = preg_split('/\s+/', trim($fen)) ?: [];

        return 'b' === ($teile[1] ?? 'w');
    }

    /**
     * Zerlegt die Figurenstellung in ein 8×8-Feld.
     *
     * @param string $stellung Erstes Feld der FEN, etwa
     *                         „rnbqkbnr/pppppppp/8/8/8/8/PPPPPPPP/RNBQKBNR"
     *
     * @throws \InvalidArgumentException Bei falscher Reihen- oder Feldanzahl
     *                                   oder unbekannten Zeichen
     *
     * @return list<list<string>> Reihen von oben (8. Reihe) nach unten, Felder
     *                            von a bis h; leere Felder als leere Zeichenkette
     */
    public static function brett(string $stellung): array
    {
        $reihen = explode('/', $stellung);

        if (8 !== \count($reihen)) {
            throw new \InvalidArgumentException(sprintf('Die Stellung braucht acht Reihen, gefunden wurden %d.', \count($reihen)));
        }

        $brett = [];

        foreach ($reihen as $nummer => $reihe) {
            $felder = [];

            foreach (str_split($reihe) as $zeichen) {
                if (ctype_digit($zeichen) && $zeichen >= '1' && $zeichen <= '8') {
                    array_push($felder, ...array_fill(0, (int) $zeichen, ''));
                } elseif (false !== strpos('KQRBNPkqrbnp', $zeichen)) {
                    $felder[] = $zeichen;
                } else {
                    throw new \InvalidArgumentException(sprintf('Unbekanntes Zeichen „%s" in Reihe %d.', $zeichen, 8 - $nummer));
                }
            }

            if (8 !== \count($felder)) {
                throw new \InvalidArgumentException(sprintf('Reihe %d hat %d statt acht Felder.', 8 - $nummer, \count($felder)));
            }

            $brett[] = $felder;
        }

        return $brett;
    }
}
