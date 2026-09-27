<?php

declare(strict_types=1);

/*
 * Contao Schachquiz Bundle.
 *
 * @license LGPL-3.0-or-later
 */

namespace Schachbulle\ContaoSchachquizBundle\Controller;

use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\FrontendUser;
use Contao\ModuleModel;
use Contao\StringUtil;
use Schachbulle\ContaoSchachquizBundle\Quiz\QuizDienst;
use Schachbulle\ContaoSchachquizBundle\Quiz\Quizeinstellung;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * Nimmt die Anfragen des Quiz-Skripts entgegen und antwortet mit JSON.
 *
 * Route: POST /_schachquiz/{modul}/{aktion}, mit {aktion} „frage" oder
 * „antwort". Die Modul-ID bestimmt, welche Themen gelten und ob Gäste
 * mitspielen dürfen; sie wird gegen die Datenbank geprüft, damit niemand
 * über eine erfundene ID an fremde Einstellungen kommt.
 *
 * Der Controller erbt nicht von AbstractController und trägt deshalb den
 * Dienst-Tag `controller.service_arguments`; sonst bliebe der Dienst privat
 * und Symfony würde ihn ohne Argumente zu erzeugen versuchen.
 */
class QuizController
{
    /**
     * Legt den Controller an.
     *
     * @param ContaoFramework       $framework    Wird vor dem Zugriff auf Models initialisiert
     * @param TokenStorageInterface $tokenStorage Liefert das angemeldete Mitglied
     * @param QuizDienst            $quiz         Die eigentliche Quizlogik
     */
    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly TokenStorageInterface $tokenStorage,
        private readonly QuizDienst $quiz,
    ) {
    }

    /**
     * Bearbeitet eine Anfrage des Quiz-Skripts.
     *
     * Die Modul-ID wird aus den Anfrageattributen gelesen und selbst nach
     * int gewandelt; Routenplatzhalter kommen immer als Zeichenkette an.
     *
     * @param Request $request Die Anfrage; erwartet im Formularinhalt bei
     *                         „frage" optional `thema`, bei „antwort" die
     *                         Liste `antwort[]`
     *
     * @return JsonResponse Die Antwort des QuizDienstes; bei unbekanntem
     *                      Modul Status 404, sonst immer 200 — Fehler stehen
     *                      dann als `status: "fehler"` im Inhalt
     */
    public function __invoke(Request $request): JsonResponse
    {
        if (!$this->istEigeneAnfrage($request)) {
            return $this->antwort(['status' => 'fehler', 'code' => 'abgewiesen', 'meldung' => 'Anfrage abgewiesen.'], 403);
        }

        $this->framework->initialize();

        $modulId = (int) $request->attributes->get('modul');
        $aktion = (string) $request->attributes->get('aktion');

        $modul = ModuleModel::findByPk($modulId);

        if (null === $modul || 'schachquiz' !== $modul->type) {
            return $this->antwort(['status' => 'fehler', 'code' => 'unbekannt', 'meldung' => 'Unbekanntes Quiz.'], 404);
        }

        $einstellung = new Quizeinstellung(
            $modulId,
            array_values(array_map('intval', StringUtil::deserialize($modul->schachquiz_themen, true))),
            (bool) $modul->schachquiz_gaeste,
        );

        $sitzung = $request->getSession();
        $mitglied = $this->mitglied();

        if ('antwort' === $aktion) {
            $daten = $this->quiz->antwort($einstellung, $mitglied, $sitzung, $request->request->all('antwort'));
        } else {
            $daten = $this->quiz->frage($einstellung, $mitglied, $sitzung, (int) $request->request->get('thema', 0));
        }

        return $this->antwort($daten);
    }

    /**
     * Prüft, ob die Anfrage vom Quiz-Skript der eigenen Seite stammt.
     *
     * Schutz gegen Anfragen, die eine fremde Seite im Namen des Besuchers
     * abschickt (CSRF): Der Kopf `X-Schachquiz` lässt sich von einer fremden
     * Seite weder mit einem Formular noch ohne CORS-Vorabfrage setzen, und
     * diese Vorabfrage beantwortet der Server nicht. Schickt der Browser
     * einen `Origin`-Kopf mit, muss er zusätzlich zur eigenen Adresse passen.
     *
     * Contaos REQUEST_TOKEN ist für diese Route abgeschaltet; warum, steht
     * in der routes.yaml.
     *
     * @param Request $request Die Anfrage
     *
     * @return bool true, wenn der Kopf gesetzt ist und ein Origin fehlt oder
     *              zur eigenen Adresse passt
     */
    private function istEigeneAnfrage(Request $request): bool
    {
        if ('1' !== $request->headers->get('X-Schachquiz')) {
            return false;
        }

        $herkunft = $request->headers->get('Origin');

        return null === $herkunft || $herkunft === $request->getSchemeAndHttpHost();
    }

    /**
     * Ermittelt das angemeldete Mitglied.
     *
     * @return int ID aus tl_member, oder 0, wenn niemand angemeldet ist oder
     *             ein Backend-Benutzer (etwa in der Vorschau) die Anfrage stellt
     */
    private function mitglied(): int
    {
        $benutzer = $this->tokenStorage->getToken()?->getUser();

        return $benutzer instanceof FrontendUser ? (int) $benutzer->id : 0;
    }

    /**
     * Baut die JSON-Antwort.
     *
     * Die Antwort ist persönlich (Wertung, offene Frage) und darf deshalb von
     * keinem Zwischenspeicher aufbewahrt werden.
     *
     * @param array<string, mixed> $daten  Der Inhalt
     * @param int                  $status HTTP-Status
     *
     * @return JsonResponse Die fertige, nicht zwischenspeicherbare Antwort
     */
    private function antwort(array $daten, int $status = 200): JsonResponse
    {
        $antwort = new JsonResponse($daten, $status);
        $antwort->setPrivate();
        $antwort->headers->addCacheControlDirective('no-store');

        return $antwort;
    }
}
