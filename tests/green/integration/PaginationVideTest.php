<?php
declare(strict_types=1);

use Spip\Test\SquelettesTestCase;
use Spip\Test\Templating;

/**
 * Vérifie le comportement correct de tests/fixtures/pagination.html
 * quand la rubrique 4 ne contient aucun article publié.
 */
final class PaginationVideTest extends SquelettesTestCase
{
    private const FIXTURE  = __DIR__ . '/../fixtures/pagination.html';
    private const RUB_ID   = 4;
    private const FALLBACK = "Aucun article publié dans cette rubrique.";

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        sql_delete('spip_articles',  'id_rubrique=' . self::RUB_ID);
        sql_delete('spip_rubriques', 'id_rubrique=' . self::RUB_ID);

        sql_insertq('spip_rubriques', [
            'id_rubrique' => self::RUB_ID,
            'titre'       => 'Rubrique vide pagination',
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
     * Quand aucun article n'est publié dans la rubrique,
     * le message de fallback doit apparaître.
     */
    public function testAfficheMessageFallbackSiAucunArticlePublie(): void
    {
        $rendered = Templating::fromString()->render(file_get_contents(self::FIXTURE));
        $this->assertStringContainsString(self::FALLBACK, $rendered);
    }
}
