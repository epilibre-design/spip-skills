<?php
declare(strict_types=1);

use Spip\Test\SquelettesTestCase;
use Spip\Test\Templating;

/**
 * Vérifie le comportement correct de tests/fixtures/includeContext.html.
 * Cette version est GREEN : le contexte est transmis à l'INCLURE via env.
 */
final class IncludeContextTest extends SquelettesTestCase
{
    private const FIXTURE = __DIR__ . '/../fixtures/includeContext.html';
    private const RUB_TARGET = 40;
    private const RUB_OTHER  = 41;

    private static ?string $oldDossierSquelettes = null;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::$oldDossierSquelettes = isset($GLOBALS['dossier_squelettes'])
            ? (string) $GLOBALS['dossier_squelettes']
            : null;

        $fixturesDir = dirname(__DIR__) . '/fixtures';
        $GLOBALS['dossier_squelettes'] = self::$oldDossierSquelettes
            ? $fixturesDir . ':' . self::$oldDossierSquelettes
            : $fixturesDir;

        sql_delete('spip_articles', 'id_rubrique IN (' . self::RUB_TARGET . ',' . self::RUB_OTHER . ')');
        sql_delete('spip_rubriques', 'id_rubrique IN (' . self::RUB_TARGET . ',' . self::RUB_OTHER . ')');

        sql_insertq('spip_rubriques', [
            'id_rubrique' => self::RUB_TARGET,
            'titre'       => 'Rubrique cible include',
            'statut'      => 'publie',
            'lang'        => 'fr',
        ]);
        sql_insertq('spip_rubriques', [
            'id_rubrique' => self::RUB_OTHER,
            'titre'       => 'Rubrique hors contexte',
            'statut'      => 'publie',
            'lang'        => 'fr',
        ]);

        sql_insertq('spip_articles', [
            'titre'       => 'Cible A',
            'statut'      => 'publie',
            'id_rubrique' => self::RUB_TARGET,
            'date'        => '2024-05-01 00:00:00',
            'lang'        => 'fr',
        ]);
        sql_insertq('spip_articles', [
            'titre'       => 'Cible B',
            'statut'      => 'publie',
            'id_rubrique' => self::RUB_TARGET,
            'date'        => '2024-04-01 00:00:00',
            'lang'        => 'fr',
        ]);
        sql_insertq('spip_articles', [
            'titre'       => 'Hors Contexte',
            'statut'      => 'publie',
            'id_rubrique' => self::RUB_OTHER,
            'date'        => '2024-06-01 00:00:00',
            'lang'        => 'fr',
        ]);
    }

    public static function tearDownAfterClass(): void
    {
        sql_delete('spip_articles', 'id_rubrique IN (' . self::RUB_TARGET . ',' . self::RUB_OTHER . ')');
        sql_delete('spip_rubriques', 'id_rubrique IN (' . self::RUB_TARGET . ',' . self::RUB_OTHER . ')');

        if (self::$oldDossierSquelettes === null) {
            unset($GLOBALS['dossier_squelettes']);
        } else {
            $GLOBALS['dossier_squelettes'] = self::$oldDossierSquelettes;
        }

        parent::tearDownAfterClass();
    }

    private function render(): string
    {
        try {
            $raw = Templating::fromString()->render(
                file_get_contents(self::FIXTURE),
                ['id_rubrique' => self::RUB_TARGET]
            );
        } catch (\Spip\Test\Exception\TemplateCompilationErrorException $e) {
            $this->fail('Erreur de compilation : ' . $e->getMessage());
        }

        return (string) preg_replace('/<!--.*?-->/s', '', $raw);
    }

    public function testAfficheLesArticlesDeLaRubriqueCible(): void
    {
        $html = $this->render();

        $this->assertStringContainsString('Cible A', $html);
        $this->assertStringContainsString('Cible B', $html);
    }

    public function testNaffichePasLesArticlesHorsContexte(): void
    {
        $this->assertStringNotContainsString('Hors Contexte', $this->render());
    }

    public function testFallbackNonAfficheQuandIlYaDesResultats(): void
    {
        $this->assertStringNotContainsString('Aucun article publie dans cette rubrique.', $this->render());
    }
}
