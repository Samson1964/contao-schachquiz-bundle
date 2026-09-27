<?php

declare(strict_types=1);

/*
 * Contao Schachquiz Bundle.
 *
 * @license LGPL-3.0-or-later
 */

namespace Schachbulle\ContaoSchachquizBundle\Command;

use Schachbulle\ContaoSchachquizBundle\Quiz\Monatsrangliste;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Konsolenbefehl `schachquiz:rangliste-sichern`: sichert die Rangliste des
 * laufenden Monats sofort, unabhängig vom Tag.
 *
 * Gedacht für den ersten Test nach der Installation und für den Fall, dass
 * der Cronjob in der ersten Woche eines Monats nicht lief. Ein schon
 * gesicherter Monat wird nicht überschrieben.
 *
 * Name und Beschreibung stehen im Attribut AsCommand: Es wirkt ab Symfony 5.3,
 * also in Contao 4.13 wie in 5; die frühere statische Eigenschaft
 * $defaultName gibt es in Symfony 7 nicht mehr.
 */
#[AsCommand(name: 'schachquiz:rangliste-sichern', description: 'Sichert die Schachquiz-Rangliste des laufenden Monats.')]
class RanglisteSichernCommand extends Command
{
    /**
     * Legt den Befehl an.
     *
     * @param Monatsrangliste $rangliste Sichert den Stand
     */
    public function __construct(private readonly Monatsrangliste $rangliste)
    {
        parent::__construct();
    }

    /**
     * Führt die Sicherung aus.
     *
     * @param InputInterface  $input  Wird nicht ausgewertet
     * @param OutputInterface $output Nimmt die Rückmeldung auf
     *
     * @return int 0 in jedem Fall; ein schon gesicherter Monat ist kein Fehler
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $ausgabe = new SymfonyStyle($input, $output);
        $jetzt = new \DateTimeImmutable();
        $anzahl = $this->rangliste->sichere($jetzt, true);

        if (null === $anzahl) {
            $ausgabe->note(sprintf('Der Monat %s ist bereits gesichert.', $jetzt->format('Y-m')));
        } else {
            $ausgabe->success(sprintf('Rangliste für %s gesichert: %d Mitglieder.', $jetzt->format('Y-m'), $anzahl));
        }

        return 0;
    }
}
