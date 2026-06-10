<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/helpers/IncludeContext.php';
require_once __DIR__ . '/mocks/IncludeContextRendererMock.php';

final class IncludeContextUnitTest extends TestCase
{
    private const RUB_TARGET = 40;

    public function testAfficheLesArticlesDeLaRubriqueCible(): void
    {
        $renderer = new IncludeContextRendererMock();

        $html = green_unit_render_include_context($renderer, self::RUB_TARGET);

        $this->assertStringContainsString('Cible A', $html);
        $this->assertStringContainsString('Cible B', $html);
        $this->assertSame(['id_rubrique' => self::RUB_TARGET], $renderer->contexts[0]);
    }

    public function testNaffichePasLesArticlesHorsContexte(): void
    {
        $renderer = new IncludeContextRendererMock();

        $html = green_unit_render_include_context($renderer, self::RUB_TARGET);

        $this->assertStringNotContainsString('Hors Contexte', $html);
    }

    public function testFallbackNonAfficheQuandIlYaDesResultats(): void
    {
        $renderer = new IncludeContextRendererMock();

        $html = green_unit_render_include_context($renderer, self::RUB_TARGET);

        $this->assertStringNotContainsString('Aucun article publie dans cette rubrique.', $html);
    }
}