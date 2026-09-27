<?php

declare(strict_types=1);

/*
 * Contao Schachquiz Bundle.
 *
 * @license LGPL-3.0-or-later
 */

namespace Schachbulle\ContaoSchachquizBundle\ContaoManager;

use Contao\CoreBundle\ContaoCoreBundle;
use Contao\ManagerPlugin\Bundle\BundlePluginInterface;
use Contao\ManagerPlugin\Bundle\Config\BundleConfig;
use Contao\ManagerPlugin\Bundle\Parser\ParserInterface;
use Contao\ManagerPlugin\Routing\RoutingPluginInterface;
use Schachbulle\ContaoSchachquizBundle\ContaoSchachquizBundle;
use Symfony\Component\Config\Loader\LoaderResolverInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\RouteCollection;

/**
 * Meldet das Bundle und seine Route beim Contao Manager an.
 *
 * Ohne diese Klasse taucht das Bundle nicht im Kernel auf, weil Contao die
 * Bundle-Liste aus den Plugins aller installierten Pakete zusammensetzt.
 */
class Plugin implements BundlePluginInterface, RoutingPluginInterface
{
    /**
     * Meldet das Bundle beim Kernel an.
     *
     * Das Bundle wird nach dem Contao-Core geladen, damit die Kerntabelle
     * tl_module bereits definiert ist, wenn die eigenen Modulfelder und
     * Paletten ergänzt werden.
     *
     * @param ParserInterface $parser Wird nicht ausgewertet, weil das Bundle
     *                                keine Konfigurationsdateien parsen lässt
     *
     * @return array<int, BundleConfig> Die Konfiguration dieses einen Bundles
     */
    public function getBundles(ParserInterface $parser): array
    {
        return [
            BundleConfig::create(ContaoSchachquizBundle::class)
                ->setLoadAfter([ContaoCoreBundle::class]),
        ];
    }

    /**
     * Lädt die Route, über die das Quiz Fragen holt und Antworten abgibt.
     *
     * Eine eigene Route ist nötig, weil ein Frontend-Modul keine reine
     * JSON-Antwort liefern kann: Contao rendert Module als Fragment und
     * bettet selbst eine per ResponseException geworfene Antwort in die
     * HTML-Seite ein.
     *
     * @param LoaderResolverInterface $resolver Findet den passenden Lader für
     *                                          die YAML-Datei (kein Lader selbst)
     * @param KernelInterface         $kernel   Wird nicht gebraucht
     *
     * @return RouteCollection|null Die Routen, oder null, wenn sich die Datei
     *                              nicht laden lässt
     */
    public function getRouteCollection(LoaderResolverInterface $resolver, KernelInterface $kernel): ?RouteCollection
    {
        $datei = __DIR__.'/../Resources/config/routes.yaml';
        $lader = $resolver->resolve($datei);

        if (false === $lader) {
            return null;
        }

        return $lader->load($datei);
    }
}
