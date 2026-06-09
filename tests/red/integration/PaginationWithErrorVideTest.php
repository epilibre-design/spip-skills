<?php
declare(strict_types=1);

use Spip\Test\SquelettesTestCase;
use Spip\Test\Templating;

/**
 * Teste le comportement CORRECT attendu de tests/fixtures/paginationWithError.html
 * quand la rubrique ne contient aucun article publié.
 * Le test ÉCHOUE intentionnellement — phase TDD RED.
 *
 * Erreur 3 — section alternative absente (ni </B_arts> ni <//B_arts>) :
 * aucun message de fallback quand la rubrique est vide.
 */
final class PaginationWithErrorVideTest extends SquelettesTestCase
{
    private const FIXTURE  = __DIR__ . '/../fixtures/paginationWithError.html';
    private const RUB_ID   = 4;
    private const FALLBACK = "Aucun article publié dans cette rubrique.";

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        sql_delete('spip_articles',  'id_rubrique=' . self::RUB_ID);
        sql_delete('spip_rubriques', 'id_rubrique=' . self::RUB_ID);

        sql_insertq('spip_rubriques', [
            'id_rubrique' => self::RUB_ID,
            'titre'       => 'Rubrique pagination vide',
            'statut'      => 'publie',
            'lang'        => 'fr',
        ]);
    }

    public static function tearDownAfterClass(): void
    {
        sql_delete('spip_articles',  'id_rubrique=' . self::RUB_ID);
        sql_delete('spip_rubriques', 'id_rubrique=' . self::RUB_ID);
        parent::tearDownAfterClass();
    }

    /**
     * Erreur 3 — section alternative absente.
     * Quand aucun article n'est publié, un message de fallback DOIT apparaître.
     * ÉCHOUE car le fixture n'a ni </B_arts> ni <//B_arts>.
     */
    public function testAfficheMessageFallbackSiAucunArticlePublie(): void
    {
        $raw = Templating::fromString()->render(
            file_get_contents(self::FIXTURE),
            ['id_rubrique' => self::RUB_ID]
        );
        $rendered = (string) preg_replace('/<!--.*?-->/s', '', $raw);

        $this->assertStringContainsString(
            self::FALLBACK,
            $rendered,
            'Le message de fallback doit apparaître quand aucun article publié n\'existe. '
            . 'Erreur : section alternative </B_arts>...<//B_arts> absente.'
        );
    }
}
