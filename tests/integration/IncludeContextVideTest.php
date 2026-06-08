<?php
declare(strict_types=1);

use Spip\Test\SquelettesTestCase;
use Spip\Test\Templating;

/**
 * Vérifie le comportement correct de tests/fixtures/includeContext.html
 * quand la rubrique cible ne contient aucun article publié.
 */
final class IncludeContextVideTest extends SquelettesTestCase
{
    private const FIXTURE  = __DIR__ . '/../fixtures/includeContext.html';
    private const RUB_ID   = 44;
    private const FALLBACK = 'Aucun article publie dans cette rubrique.';

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

        sql_delete('spip_articles', 'id_rubrique=' . self::RUB_ID);
        sql_delete('spip_rubriques', 'id_rubrique=' . self::RUB_ID);

        sql_insertq('spip_rubriques', [
            'id_rubrique' => self::RUB_ID,
            'titre'       => 'Rubrique vide include context',
            'statut'      => 'publie',
            'lang'        => 'fr',
        ]);
    }

    public static function tearDownAfterClass(): void
    {
        sql_delete('spip_articles', 'id_rubrique=' . self::RUB_ID);
        sql_delete('spip_rubriques', 'id_rubrique=' . self::RUB_ID);

        if (self::$oldDossierSquelettes === null) {
            unset($GLOBALS['dossier_squelettes']);
        } else {
            $GLOBALS['dossier_squelettes'] = self::$oldDossierSquelettes;
        }

        parent::tearDownAfterClass();
    }

    public function testAfficheFallbackQuandRubriqueVide(): void
    {
        $raw = Templating::fromString()->render(
            file_get_contents(self::FIXTURE),
            ['id_rubrique' => self::RUB_ID]
        );
        $rendered = (string) preg_replace('/<!--.*?-->/s', '', $raw);

        $this->assertStringContainsString(self::FALLBACK, $rendered);
    }
}
