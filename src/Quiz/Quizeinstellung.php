<?php

declare(strict_types=1);

/*
 * Contao Schachquiz Bundle.
 *
 * @license LGPL-3.0-or-later
 */

namespace Schachbulle\ContaoSchachquizBundle\Quiz;

/**
 * Die Einstellungen eines Quiz-Frontendmoduls, soweit die Quizlogik sie braucht.
 *
 * Die Klasse entkoppelt QuizDienst vom ModuleModel. Der Dienst bleibt damit
 * ohne gebootetes Contao prüfbar.
 */
final class Quizeinstellung
{
    /**
     * Legt die Einstellung an.
     *
     * @param int       $modul         ID des Frontendmoduls; unterscheidet
     *                                 offene Fragen mehrerer Quizze einer Sitzung
     * @param list<int> $themen        Erlaubte Themen; leer bedeutet „alle
     *                                 veröffentlichten Themen"
     * @param bool      $gaesteErlaubt Ob Besucher ohne Anmeldung mitspielen dürfen
     */
    public function __construct(
        public readonly int $modul,
        public readonly array $themen,
        public readonly bool $gaesteErlaubt,
    ) {
    }

    /**
     * Prüft, ob ein vom Besucher gewähltes Thema zu diesem Quiz gehört.
     *
     * @param int $thema ID des gewählten Themas, 0 für „alle"
     *
     * @return bool true für 0 und für jedes erlaubte Thema; bei leerer
     *              Themenliste entscheidet erst die Datenbank, ob das Thema
     *              veröffentlicht ist
     */
    public function erlaubtThema(int $thema): bool
    {
        return 0 === $thema || [] === $this->themen || \in_array($thema, $this->themen, true);
    }
}
