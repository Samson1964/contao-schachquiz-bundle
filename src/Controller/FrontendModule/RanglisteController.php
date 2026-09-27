<?php

declare(strict_types=1);

/*
 * Contao Schachquiz Bundle.
 *
 * @license LGPL-3.0-or-later
 */

namespace Schachbulle\ContaoSchachquizBundle\Controller\FrontendModule;

use Contao\CoreBundle\Controller\FrontendModule\AbstractFrontendModuleController;
use Contao\FrontendUser;
use Contao\ModuleModel;
use Contao\Template;
use Schachbulle\ContaoSchachquizBundle\Quiz\Rangliste;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * Frontend-Modul „Schachquiz-Rangliste": die besten Mitglieder nach ihrer
 * Glicko-2-Wertung.
 *
 * Ist ein Mitglied angemeldet, wird seine Zeile hervorgehoben; steht es nicht
 * unter den gezeigten Plätzen, erscheint es zusätzlich unter der Liste mit
 * seinem tatsächlichen Rang.
 */
class RanglisteController extends AbstractFrontendModuleController
{
    /**
     * Legt den Controller an.
     *
     * @param Rangliste             $rangliste    Liest die Plätze aus der Datenbank
     * @param TokenStorageInterface $tokenStorage Liefert das angemeldete Mitglied
     */
    public function __construct(
        private readonly Rangliste $rangliste,
        private readonly TokenStorageInterface $tokenStorage,
    ) {
    }

    /**
     * Erzeugt die Ausgabe des Moduls.
     *
     * Die Ausgabe hängt vom angemeldeten Mitglied ab (hervorgehobene Zeile)
     * und wird deshalb als privat gekennzeichnet.
     *
     * @param Template    $template Das Template mod_schachquiz_rangliste
     * @param ModuleModel $model    Der Datensatz des Moduls
     * @param Request     $request  Die laufende Anfrage
     *
     * @return Response Die Antwort mit der Rangliste
     */
    protected function getResponse(Template $template, ModuleModel $model, Request $request): Response
    {
        $benutzer = $this->tokenStorage->getToken()?->getUser();
        $mitglied = $benutzer instanceof FrontendUser ? (int) $benutzer->id : 0;

        $anzahl = max(1, (int) ($model->schachquiz_anzahl ?: 20));
        $mindestens = max(0, (int) $model->schachquiz_mindestzahl);
        $format = (string) ($model->schachquiz_namensformat ?: 'kurz');

        $zeilen = $this->rangliste->plaetze($anzahl, $mindestens, $format);
        $eigene = null;

        if ($mitglied > 0) {
            $gefunden = false;

            foreach ($zeilen as &$zeile) {
                $zeile['eigene'] = $zeile['member'] === $mitglied;
                $gefunden = $gefunden || $zeile['eigene'];
            }

            unset($zeile);

            if (!$gefunden) {
                $eigene = $this->rangliste->eigenerPlatz($mitglied, $mindestens, $format);
            }
        }

        $template->zeilen = $zeilen;
        $template->eigene = $eigene;
        $template->mindestens = $mindestens;

        $GLOBALS['TL_CSS']['schachquiz'] = 'bundles/contaoschachquiz/schachquiz.css|static';

        $antwort = $template->getResponse();

        if ($mitglied > 0) {
            $antwort->setPrivate();
        }

        return $antwort;
    }
}
