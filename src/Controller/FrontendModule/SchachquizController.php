<?php

declare(strict_types=1);

/*
 * Contao Schachquiz Bundle.
 *
 * @license LGPL-3.0-or-later
 */

namespace Schachbulle\ContaoSchachquizBundle\Controller\FrontendModule;

use Contao\CoreBundle\Controller\FrontendModule\AbstractFrontendModuleController;
use Contao\ModuleModel;
use Contao\StringUtil;
use Contao\Template;
use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Frontend-Modul „Schachquiz": der Spieltisch mit Frage, Diagramm, Antworten
 * und den Schachfiguren, die mitfiebern.
 *
 * Das Modul gibt nur das Gerüst aus; Fragen und Auswertungen holt das Skript
 * über die Route `schachquiz_aktion`. Die Seite selbst enthält damit nichts
 * Persönliches und darf zwischengespeichert werden.
 *
 * Registriert wird das Modul über den Dienst-Tag `contao.frontend_module`
 * in der services.yaml; der Tag wirkt in Contao 4.13 wie in Contao 5.
 */
class SchachquizController extends AbstractFrontendModuleController
{
    /**
     * Legt den Controller an.
     *
     * @param Connection            $db     Liest die wählbaren Themen
     * @param UrlGeneratorInterface $router Erzeugt die Adresse der Schnittstelle
     */
    public function __construct(
        private readonly Connection $db,
        private readonly UrlGeneratorInterface $router,
    ) {
    }

    /**
     * Erzeugt die Ausgabe des Moduls.
     *
     * Der Parametertyp `Template` und der Rückgabetyp `Response` erfüllen die
     * abstrakten Signaturen beider Contao-Fassungen: `Template` ist die
     * Elternklasse des in Contao 5 verwendeten `FragmentTemplate`, und
     * `Response` ist enger als das in 4.13 deklarierte `?Response`.
     *
     * @param Template    $template Das Template mod_schachquiz
     * @param ModuleModel $model    Der Datensatz des Moduls
     * @param Request     $request  Die laufende Anfrage
     *
     * @return Response Die Antwort mit dem Quizgerüst
     */
    protected function getResponse(Template $template, ModuleModel $model, Request $request): Response
    {
        $template->modulId = (int) $model->id;
        $template->adresseFrage = $this->router->generate('schachquiz_aktion', ['modul' => $model->id, 'aktion' => 'frage']);
        $template->adresseAntwort = $this->router->generate('schachquiz_aktion', ['modul' => $model->id, 'aktion' => 'antwort']);
        $template->themen = $this->themen(array_values(array_map('intval', StringUtil::deserialize($model->schachquiz_themen, true))));
        $template->gaesteErlaubt = (bool) $model->schachquiz_gaeste;
        $template->animationen = !$model->schachquiz_ohneAnimation;

        // Absolute Adresse, weil das Skript sie in <use href> einsetzt und eine
        // relative Angabe dort nicht verlässlich über <base> aufgelöst wird.
        $template->figuren = $request->getBasePath().'/bundles/contaoschachquiz/figuren/cburnett.svg';
        $template->texte = $this->texte();

        $GLOBALS['TL_CSS']['schachquiz'] = 'bundles/contaoschachquiz/schachquiz.css|static';
        $GLOBALS['TL_JAVASCRIPT']['schachquiz'] = 'bundles/contaoschachquiz/schachquiz.js|static';

        return $template->getResponse();
    }

    /**
     * Liest die Themen, zwischen denen der Besucher wählen kann.
     *
     * @param list<int> $erlaubt Die im Modul gewählten Themen; leer für alle
     *
     * @return list<array{id: int, titel: string, anzahl: int}> Veröffentlichte
     *                                                          Themen mit mindestens
     *                                                          einer veröffentlichten
     *                                                          Frage, nach Titel sortiert
     */
    private function themen(array $erlaubt): array
    {
        $sql = "SELECT t.id, t.title, COUNT(i.id) AS anzahl FROM tl_schachquiz t
            INNER JOIN tl_schachquiz_items i ON i.pid = t.id AND i.published = '1'
            WHERE t.published = '1'";

        if ([] !== $erlaubt) {
            $sql .= ' AND t.id IN ('.implode(',', $erlaubt).')';
        }

        $sql .= ' GROUP BY t.id, t.title ORDER BY t.title';

        return array_map(
            static fn (array $zeile): array => ['id' => (int) $zeile['id'], 'titel' => (string) $zeile['title'], 'anzahl' => (int) $zeile['anzahl']],
            $this->db->fetchAllAssociative($sql)
        );
    }

    /**
     * Stellt die Beschriftungen für das Skript zusammen.
     *
     * Die Texte kommen aus der Sprachdatei, damit sie sich wie jede andere
     * Contao-Beschriftung über eigene Sprachdateien anpassen lassen.
     *
     * @return array<string, string> Schlüssel und Text, für das Skript als JSON
     */
    private function texte(): array
    {
        $texte = [];

        foreach ((array) ($GLOBALS['TL_LANG']['schachquiz'] ?? []) as $schluessel => $text) {
            if (\is_string($text)) {
                $texte[$schluessel] = $text;
            }
        }

        return $texte;
    }
}
