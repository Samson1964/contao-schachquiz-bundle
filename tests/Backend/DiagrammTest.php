<?php

declare(strict_types=1);

/*
 * Contao Schachquiz Bundle.
 *
 * @license LGPL-3.0-or-later
 */

namespace Schachbulle\ContaoSchachquizBundle\Tests\Backend;

use PHPUnit\Framework\TestCase;
use Schachbulle\ContaoSchachquizBundle\Backend\Diagramm;

/**
 * Prüft das SVG-Balkendiagramm der Statistik.
 */
class DiagrammTest extends TestCase
{
    /**
     * Die Skala endet auf einer durch vier teilbaren, runden Zahl.
     */
    public function testSkala(): void
    {
        $this->assertSame(4, Diagramm::skala(0));
        $this->assertSame(8, Diagramm::skala(7));
        $this->assertSame(44, Diagramm::skala(43));
        $this->assertSame(520, Diagramm::skala(512));
    }

    /**
     * Ohne Werte gibt es kein Diagramm, damit das Template einen Hinweis zeigen kann.
     */
    public function testLeer(): void
    {
        $this->assertSame('', Diagramm::balken([], 'leer'));
    }

    /**
     * Mit zweiter Reihe entstehen je Balken zwei Rechtecke, leere Werte keines;
     * Beschriftungen werden maskiert.
     */
    public function testZweiReihen(): void
    {
        $svg = Diagramm::balken([
            ['titel' => 'Jan', 'wert' => 3, 'wert2' => 10],
            ['titel' => '<b>', 'wert' => 0, 'wert2' => 0],
        ], 'Test & Probe');

        $this->assertSame(2, substr_count($svg, '<rect'));
        $this->assertStringContainsString(Diagramm::FARBE_HELL, $svg);
        $this->assertStringContainsString('&lt;b&gt;', $svg);
        $this->assertStringContainsString('aria-label="Test &amp; Probe"', $svg);
    }
}
