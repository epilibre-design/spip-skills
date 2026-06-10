<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/helpers/BoucleArticlesOrder.php';

final class BoucleArticlesOrderUnitTest extends TestCase
{
    private const RUBRIQUE_CIBLE = 7;

    public function testRetourneLesCinqArticlesPubliesLesPlusRecents(): void
    {
        $this->assertSame(
            '106,105,104,103,102,',
            green_unit_boucle_articles_order($this->articlesChronologiques(), self::RUBRIQUE_CIBLE, 5)
        );
    }

    public function testTrieLesArticlesAvantDAppliquerLaLimite(): void
    {
        $articles = array_reverse($this->articlesChronologiques());

        $this->assertSame(
            '106,105,104,103,102,',
            green_unit_boucle_articles_order($articles, self::RUBRIQUE_CIBLE, 5)
        );
    }

    public function testIgnoreLesBrouillonsEtLesAutresRubriques(): void
    {
        $articles = array_merge(
            $this->articlesChronologiques(),
            [
                ['id_article' => 200, 'id_rubrique' => self::RUBRIQUE_CIBLE, 'statut' => 'prepa', 'date' => '2024-01-01 00:00:00'],
                ['id_article' => 201, 'id_rubrique' => 8, 'statut' => 'publie', 'date' => '2024-02-01 00:00:00'],
            ]
        );

        $this->assertSame(
            '106,105,104,103,102,',
            green_unit_boucle_articles_order($articles, self::RUBRIQUE_CIBLE, 5)
        );
    }

    private function articlesChronologiques(): array
    {
        return [
            ['id_article' => 101, 'id_rubrique' => self::RUBRIQUE_CIBLE, 'statut' => 'publie', 'date' => '2020-01-01 00:00:00'],
            ['id_article' => 102, 'id_rubrique' => self::RUBRIQUE_CIBLE, 'statut' => 'publie', 'date' => '2020-03-15 00:00:00'],
            ['id_article' => 103, 'id_rubrique' => self::RUBRIQUE_CIBLE, 'statut' => 'publie', 'date' => '2020-06-30 00:00:00'],
            ['id_article' => 104, 'id_rubrique' => self::RUBRIQUE_CIBLE, 'statut' => 'publie', 'date' => '2021-01-10 00:00:00'],
            ['id_article' => 105, 'id_rubrique' => self::RUBRIQUE_CIBLE, 'statut' => 'publie', 'date' => '2021-08-20 00:00:00'],
            ['id_article' => 106, 'id_rubrique' => self::RUBRIQUE_CIBLE, 'statut' => 'publie', 'date' => '2022-02-14 00:00:00'],
        ];
    }
}