<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/helpers/Pagination.php';
require_once __DIR__ . '/mocks/PaginationRendererMock.php';

final class PaginationUnitTest extends TestCase
{
    public function testNafficheQueLesArticlesPublies(): void
    {
        $renderer = new PaginationRendererMock();

        $html = green_unit_render_pagination($this->articles(), $renderer, 5);

        $this->assertStringNotContainsString('Brouillon', $html);
        $this->assertCount(1, $renderer->calls);
        $this->assertCount(5, $renderer->calls[0]['page']);
    }

    public function testLaListeEstStructureeAvecUl(): void
    {
        $renderer = new PaginationRendererMock();

        $html = green_unit_render_pagination($this->articles(), $renderer, 5);

        $this->assertStringContainsString('<ul>', $html);
        $this->assertStringContainsString('</ul>', $html);
    }

    public function testLaPremierePageAfficheCinqArticles(): void
    {
        $renderer = new PaginationRendererMock();

        $html = green_unit_render_pagination($this->articles(), $renderer, 5);

        $this->assertSame(5, substr_count($html, '<li><a href="#">'));
    }

    public function testAfficheUneNavigationVersLaPageSuivante(): void
    {
        $renderer = new PaginationRendererMock();

        $html = green_unit_render_pagination($this->articles(), $renderer, 5);

        $this->assertStringContainsString('debut_arts=5', $html);
    }

    private function articles(): array
    {
        return [
            ['titre' => 'Article 1', 'statut' => 'publie', 'date' => '2020-01-01 00:00:00'],
            ['titre' => 'Article 2', 'statut' => 'publie', 'date' => '2020-06-01 00:00:00'],
            ['titre' => 'Article 3', 'statut' => 'publie', 'date' => '2021-01-01 00:00:00'],
            ['titre' => 'Article 4', 'statut' => 'publie', 'date' => '2021-06-01 00:00:00'],
            ['titre' => 'Article 5', 'statut' => 'publie', 'date' => '2022-01-01 00:00:00'],
            ['titre' => 'Article 6', 'statut' => 'publie', 'date' => '2022-06-01 00:00:00'],
            ['titre' => 'Article 7', 'statut' => 'publie', 'date' => '2023-01-01 00:00:00'],
            ['titre' => 'Brouillon',  'statut' => 'prepa',  'date' => '2024-01-01 00:00:00'],
        ];
    }
}