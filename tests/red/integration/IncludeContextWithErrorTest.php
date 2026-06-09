<?php
declare(strict_types=1);

use Spip\Test\SquelettesTestCase;
use Spip\Test\Templating;

/**
 * Teste le comportement CORRECT attendu de tests/fixtures/includeContextWithError.html.
 * Les tests ÉCHOUENT intentionnellement — phase TDD RED.
 *
 * Erreur dans le fixture :
 *   - CONTEXTE_INCLURE_NON_TRANSMIS : #INCLURE sans env ni id_rubrique explicite.
 *     Le fragment perd le filtre contextuel {id_rubrique}.
 */
final class IncludeContextWithErrorTest extends SquelettesTestCase
{
    private const FIXTURE = __DIR__ . '/../fixtures/includeContextWithError.html';
    private const RUB_TARGET = 42;
    private const RUB_OTHER  = 43;

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
            'titre'       => 'Rubrique cible include with error',
            'statut'      => 'publie',
            'lang'        => 'fr',
        ]);
        sql_insertq('spip_rubriques', [
            'id_rubrique' => self::RUB_OTHER,
            'titre'       => 'Rubrique contaminee',
            'statut'      => 'publie',
            'lang'        => 'fr',
        ]);

        sql_insertq('spip_articles', [
            'titre'       => 'Cible Error A',
            'statut'      => 'publie',
            'id_rubrique' => self::RUB_TARGET,
            'date'        => '2024-05-01 00:00:00',
            'lang'        => 'fr',
        ]);
        sql_insertq('spip_articles', [
            'titre'       => 'Cible Error B',
            'statut'      => 'publie',
            'id_rubrique' => self::RUB_TARGET,
            'date'        => '2024-04-01 00:00:00',
            'lang'        => 'fr',
        ]);
        sql_insertq('spip_articles', [
            'titre'       => 'Contamination Include',
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

    /**
     * Comportement correct attendu : la liste incluse ne doit pas tomber en fallback vide.
     * ÉCHOUE car l'INCLURE ne reçoit pas le contexte id_rubrique et le fragment ne trouve rien.
     */
    public function testNaffichePasLeFallbackVide(): void
    {
        $this->assertStringNotContainsString(
            'Aucun article publie dans cette rubrique.',
            $this->render(),
            'Le fallback vide apparaît : le contexte de l\'INCLURE n\'est pas transmis.'
        );
    }

    /**
     * Comportement correct attendu : seuls les 2 articles cibles sont listés.
     * ÉCHOUE si la liste est contaminée par d'autres rubriques.
     */
    public function testAfficheExactementDeuxArticlesCibles(): void
    {
        $html = $this->render();

        $this->assertStringContainsString('Cible Error A', $html);
        $this->assertStringContainsString('Cible Error B', $html);
        $this->assertSame(
            2,
            substr_count($html, '<li><a href'),
            'La liste incluse doit contenir exactement 2 articles de la rubrique cible.'
        );
    }
}
