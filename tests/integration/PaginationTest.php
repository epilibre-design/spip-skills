<?php
declare(strict_types=1);

use Spip\Test\SquelettesTestCase;
use Spip\Test\Templating;

/**
 * Vérifie le comportement correct de tests/fixtures/pagination.html.
 * Tous les tests doivent PASSER — fixture correct (version verte).
 *
 * Rubrique 4 avec 7 articles publiés + 1 brouillon.
 * Fixture : {id_rubrique=4}{statut=publie}{par date}{inverse}{pagination 5}
 * #PAGINATION dans <B_arts> (partie conditionnelle, avant la liste).
 */
final class PaginationTest extends SquelettesTestCase
{
    private const FIXTURE = __DIR__ . '/../fixtures/pagination.html';
    private const RUB_ID  = 4;

    private static array $articleIds = [];

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        sql_delete('spip_articles',  'id_rubrique=' . self::RUB_ID);
        sql_delete('spip_rubriques', 'id_rubrique=' . self::RUB_ID);

        sql_insertq('spip_rubriques', [
            'id_rubrique' => self::RUB_ID,
            'titre'       => 'Rubrique pagination correcte',
            'statut'      => 'publie',
            'lang'        => 'fr',
        ]);

        $articles = [
            ['titre' => 'Article 1', 'statut' => 'publie', 'date' => '2020-01-01 00:00:00'],
            ['titre' => 'Article 2', 'statut' => 'publie', 'date' => '2020-06-01 00:00:00'],
            ['titre' => 'Article 3', 'statut' => 'publie', 'date' => '2021-01-01 00:00:00'],
            ['titre' => 'Article 4', 'statut' => 'publie', 'date' => '2021-06-01 00:00:00'],
            ['titre' => 'Article 5', 'statut' => 'publie', 'date' => '2022-01-01 00:00:00'],
            ['titre' => 'Article 6', 'statut' => 'publie', 'date' => '2022-06-01 00:00:00'],
            ['titre' => 'Article 7', 'statut' => 'publie', 'date' => '2023-01-01 00:00:00'],
            ['titre' => 'Brouillon',  'statut' => 'prepa',  'date' => '2024-01-01 00:00:00'],
        ];
        foreach ($articles as $data) {
            self::$articleIds[] = (int) sql_insertq('spip_articles', [
                'titre'       => $data['titre'],
                'statut'      => $data['statut'],
                'id_rubrique' => self::RUB_ID,
                'date'        => $data['date'],
                'lang'        => 'fr',
            ]);
        }
    }

    public static function tearDownAfterClass(): void
    {
        sql_delete('spip_articles',  'id_rubrique=' . self::RUB_ID);
        sql_delete('spip_rubriques', 'id_rubrique=' . self::RUB_ID);
        parent::tearDownAfterClass();
    }

    private function render(): string
    {
        return Templating::fromString()->render(file_get_contents(self::FIXTURE));
    }

    /**
     * Seuls les articles publiés doivent apparaître — pas les brouillons.
     */
    public function testNAffichePassLesArticlesNonPublies(): void
    {
        $this->assertStringNotContainsString('Brouillon', $this->render());
    }

    /**
     * La liste d'articles doit être structurée avec <ul>.
     */
    public function testListeEstStructureeAvecUl(): void
    {
        $this->assertStringContainsString('<ul>', $this->render());
    }

    /**
     * Avec 7 articles et {pagination 5}, la 1ère page affiche exactement 5 articles.
     * On compte uniquement <li><a href (articles), pas <li class= (items de pagination).
     */
    public function testPaginationLimiteAcinqArticlesParPage(): void
    {
        $this->assertSame(
            5,
            substr_count($this->render(), '<li><a href'),
            'La 1ère page doit afficher exactement 5 articles (pagination 5).'
        );
    }

    /**
     * Avec 7 articles et {pagination 5}, #PAGINATION génère un lien vers la page 2.
     * SPIP utilise le paramètre debut_arts (offset) pour la navigation.
     */
    public function testPaginationAfficheNavigationEntreLesPages(): void
    {
        $this->assertStringContainsString(
            'debut_arts=',
            $this->render(),
            '#PAGINATION doit générer un lien de navigation (debut_arts=) avec 7 articles et pagination 5.'
        );
    }
}
