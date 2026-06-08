<?php
declare(strict_types=1);

use Spip\Test\SquelettesTestCase;
use Spip\Test\Templating;

/**
 * Tests décrivant le comportement CORRECT attendu de tests/fixtures/boucleErronnee.html.
 *
 * Ces tests ÉCHOUENT intentionnellement car le squelette contient 4 erreurs:
 *   1. Tri manquant: {0,5} sans {par date}{inverse} → articles pas triés du plus récent au plus ancien
 *   2. #UURL_ARTICLE — balise inconnue → href vide
 *   3. #TITTRE       — balise inconnue → texte du lien vide
 *   4. ##DATE        — double dièse → rend le texte littéral "#DATE" au lieu de la date formatée
 *
 * Pour corriger le squelette, il faut:
 *   1. Ajouter {par date}{inverse} dans les critères de la BOUCLE
 *   2. Remplacer #UURL_ARTICLE par #URL_ARTICLE
 *   3. Remplacer #TITTRE par #TITRE
 *   4. Remplacer ##DATE par #DATE
 */
final class BoucleErronneeTest extends SquelettesTestCase
{
    private const FIXTURE = __DIR__ . '/../fixtures/boucleErronnee.html';
    private const RUB_ID  = 3;

    /** @var int[] Article IDs inserted, oldest → newest */
    private static array $articleIds = [];

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        // Force-clean rubrique 3 and its articles
        sql_delete('spip_articles',  'id_rubrique=' . self::RUB_ID);
        sql_delete('spip_rubriques', 'id_rubrique=' . self::RUB_ID);

        sql_insertq('spip_rubriques', [
            'id_rubrique' => self::RUB_ID,
            'titre'       => 'Rubrique test BoucleErronnee',
            'statut'      => 'publie',
            'lang'        => 'fr',
        ]);

        // 6 articles with strictly increasing dates (oldest → newest)
        $articles = [
            ['titre' => 'Article Ancien',      'date' => '2020-01-01 00:00:00'],
            ['titre' => 'Article Fevrier',     'date' => '2020-03-15 00:00:00'],
            ['titre' => 'Article Juin',        'date' => '2020-06-30 00:00:00'],
            ['titre' => 'Article Janvier2021', 'date' => '2021-01-10 00:00:00'],
            ['titre' => 'Article Aout',        'date' => '2021-08-20 00:00:00'],
            ['titre' => 'Article Recent',      'date' => '2022-02-14 00:00:00'],
        ];

        foreach ($articles as $data) {
            self::$articleIds[] = (int) sql_insertq('spip_articles', [
                'titre'       => $data['titre'],
                'statut'      => 'publie',
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

    /**
     * Test 1 — Les titres des articles doivent apparaître dans le rendu.
     *
     * ÉCHOUE car #TITTRE est une balise inconnue (doit être #TITRE) et rend une chaîne vide.
     */
    public function testAfficheLesTitresDesArticles(): void
    {
        $rendered = Templating::fromString()->render(file_get_contents(self::FIXTURE));
        $this->assertStringContainsString(
            'Article Recent',
            $rendered,
            'Le titre de l\'article le plus récent doit apparaître. Erreur: #TITTRE est une balise inconnue (doit être #TITRE).'
        );
    }

    /**
     * Test 2 — Les liens vers les articles doivent avoir un href non vide.
     *
     * ÉCHOUE car #UURL_ARTICLE est une balise inconnue (doit être #URL_ARTICLE) et rend un href vide.
     */
    public function testAfficheDesLiensValides(): void
    {
        $rendered = Templating::fromString()->render(file_get_contents(self::FIXTURE));
        $this->assertStringNotContainsString(
            'href=""',
            $rendered,
            'Les liens des articles ne doivent pas avoir un href vide. Erreur: #UURL_ARTICLE est une balise inconnue (doit être #URL_ARTICLE).'
        );
    }

    /**
     * Test 3 — Les dates doivent apparaître formatées, pas comme texte littéral "#DATE".
     *
     * ÉCHOUE car ##DATE (double dièse) rend le texte littéral "#DATE" au lieu de la date formatée.
     */
    public function testAfficheLaDateFormatee(): void
    {
        $rendered = Templating::fromString()->render(file_get_contents(self::FIXTURE));
        $this->assertStringNotContainsString(
            '#DATE',
            $rendered,
            'La balise ##DATE ne doit pas rendre le texte littéral "#DATE". Erreur: ##DATE doit être #DATE.'
        );
    }

    /**
     * Test 4 — Les articles doivent être triés du plus récent au plus ancien.
     *
     * ÉCHOUE car la BOUCLE du squelette est dépourvue de {par date}{inverse}:
     * la BOUCLE telle que rédigée ne produit pas l'ordre chronologique inverse attendu.
     */
    public function testArticlesTriesParDateDecroissante(): void
    {
        $ids = self::$articleIds;
        $correctOrder = implode(',', array_slice(array_reverse($ids), 0, 5)) . ',';

        // Reproduce the fixture's BOUCLE exactly (without {par date}{inverse})
        $fixtureSort = sprintf(
            '<BOUCLE_a(ARTICLES){id_rubrique=%d}{statut=publie}{0,5}>#ID_ARTICLE,</BOUCLE_a>',
            self::RUB_ID
        );

        // Assert the fixture's BOUCLE produces newest-first order — will FAIL
        // because {par date}{inverse} is missing.
        $this->assertEqualsCode(
            $correctOrder,
            $fixtureSort,
            'Les articles doivent être affichés du plus récent au plus ancien. Critères manquants: {par date}{inverse}.'
        );
    }
}
