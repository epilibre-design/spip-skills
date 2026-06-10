<?php
declare(strict_types=1);

use Spip\Test\SquelettesTestCase;
use Spip\Test\Templating;

/**
 * Teste le comportement CORRECT attendu de tests/fixtures/includeContextWithError.html
 * quand la rubrique cible est vide.
 * Le test ÉCHOUE intentionnellement — phase TDD RED.
 *
 * Erreur du fixture : INCLURE sans transmission de contexte (env/id_rubrique).
 */
final class IncludeContextWithErrorVideTest extends SquelettesTestCase
{
    private const FIXTURE   = __DIR__ . '/../fixtures/includeContextWithError.html';
    private const RUB_EMPTY = 45;
    private const RUB_OTHER = 46;
    private const FALLBACK  = 'Aucun article publie dans cette rubrique.';

    private static ?string $oldDossierSquelettes = null;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::$oldDossierSquelettes = isset($GLOBALS['dossier_squelettes'])
            ? (string) $GLOBALS['dossier_squelettes']
            : null;

        $fixturesDir = '../../../red/fixtures:../../../green/fixtures';
        $GLOBALS['dossier_squelettes'] = self::$oldDossierSquelettes
            ? $fixturesDir . ':' . self::$oldDossierSquelettes
            : $fixturesDir;

        sql_delete('spip_articles', 'id_rubrique IN (' . self::RUB_EMPTY . ',' . self::RUB_OTHER . ')');
        sql_delete('spip_rubriques', 'id_rubrique IN (' . self::RUB_EMPTY . ',' . self::RUB_OTHER . ')');

        sql_insertq('spip_rubriques', [
            'id_rubrique' => self::RUB_EMPTY,
            'titre'       => 'Rubrique vide include with error',
            'statut'      => 'publie',
            'lang'        => 'fr',
        ]);
        sql_insertq('spip_rubriques', [
            'id_rubrique' => self::RUB_OTHER,
            'titre'       => 'Rubrique externe include with error',
            'statut'      => 'publie',
            'lang'        => 'fr',
        ]);

        sql_insertq('spip_articles', [
            'titre'       => 'Article Externe',
            'statut'      => 'publie',
            'id_rubrique' => self::RUB_OTHER,
            'date'        => '2024-06-01 00:00:00',
            'lang'        => 'fr',
        ]);
    }

    public static function tearDownAfterClass(): void
    {
        sql_delete('spip_articles', 'id_rubrique IN (' . self::RUB_EMPTY . ',' . self::RUB_OTHER . ')');
        sql_delete('spip_rubriques', 'id_rubrique IN (' . self::RUB_EMPTY . ',' . self::RUB_OTHER . ')');

        if (self::$oldDossierSquelettes === null) {
            unset($GLOBALS['dossier_squelettes']);
        } else {
            $GLOBALS['dossier_squelettes'] = self::$oldDossierSquelettes;
        }

        parent::tearDownAfterClass();
    }

    /**
     * Comportement correct attendu : la rubrique cible est vide,
     * le message de fallback doit apparaître.
     * ÉCHOUE car l'INCLURE perd le contexte et peut afficher l'article externe.
     */
    public function testAfficheFallbackQuandRubriqueCibleVide(): void
    {
        $raw = Templating::fromString()->render(
            file_get_contents(self::FIXTURE),
            ['id_rubrique' => self::RUB_EMPTY]
        );
        $rendered = (string) preg_replace('/<!--.*?-->/s', '', $raw);

        $this->assertStringContainsString(
            self::FALLBACK,
            $rendered,
            'Le fallback attendu ne s\'affiche pas: le contexte de l\'INCLURE est perdu.'
        );
    }
}
