<?php

declare(strict_types=1);

/*
 * Contao Schachquiz Bundle.
 *
 * @license LGPL-3.0-or-later
 */

namespace Schachbulle\ContaoSchachquizBundle\Cron;

use Psr\Log\LoggerInterface;
use Schachbulle\ContaoSchachquizBundle\Quiz\Monatsrangliste;

/**
 * Cronjob: sichert am Monatsersten die Rangliste.
 *
 * Registriert über den Dienst-Tag `contao.cronjob` mit dem Intervall
 * „monthly“; Contao macht daraus in 4.13 wie in 5 den Ausdruck „@monthly“
 * (Erster des Monats, 0 Uhr) und holt einen verpassten Lauf beim nächsten
 * Cron-Aufruf nach.
 *
 * Beim allerersten Aufruf nach der Installation führt Contao jeden Job sofort
 * aus. Monatsrangliste::sichere() lehnt das ab, wenn der Monat schon älter als
 * eine Woche ist — sonst stünde der Installationstag als Monatsstand da.
 */
class MonatsranglisteCron
{
    /**
     * Legt den Cronjob an.
     *
     * @param Monatsrangliste $rangliste Sichert den Stand
     * @param LoggerInterface $logger    Schreibt ins Contao-Systemprotokoll
     */
    public function __construct(
        private readonly Monatsrangliste $rangliste,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Führt die Sicherung aus.
     *
     * Protokolliert wird nur, wenn tatsächlich gesichert wurde; ein Lauf ohne
     * Arbeit (Monat schon gesichert) soll das Protokoll nicht füllen.
     */
    public function __invoke(): void
    {
        $jetzt = new \DateTimeImmutable();
        $anzahl = $this->rangliste->sichere($jetzt);

        if (null !== $anzahl) {
            $this->logger->info(sprintf('Schachquiz: Rangliste für %s gesichert (%d Mitglieder).', $jetzt->format('Y-m'), $anzahl));
        }
    }
}
