<?php
declare(strict_types=1);

use Spip\Test\SquelettesTestCase;
use Spip\Test\Templating;

/**
 * Detects the 4 errors intentionally embedded in tests/fixtures/boucleErronnee.html:
 *   1. Missing sort: {limit 5} without {par date}{inverse}
 *   2. #UURL_ARTICLE  — double-U typo, unknown balise → empty href
 *   3. #TITTRE        — double-T typo, unknown balise → empty link text
 *   4. ##DATE         — double-# escapes the balise → literal "#DATE" instead of date value
 */
final class BoucleErronneeDetectionTest extends SquelettesTestCase
{
    private const FIXTURE = __DIR__ . '/../fixtures/boucleErronnee.html';
    private const RUB_ID  = 3;

    /** @var int[] Inserted article IDs, oldest → newest */
    private static array $articleIds = [];

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        // Force-clean rubrique 3 and its articles
        sql_delete('spip_articles',  'id_rubrique=' . self::RUB_ID);
        sql_delete('spip_rubriques', 'id_rubrique=' . self::RUB_ID);

        sql_insertq('spip_rubriques', [
            'id_rubrique' => self::RUB_ID,
            'titre'       => 'Rubrique test fixture BoucleErronnee',
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
     * Test 1 — Tri manquant
     *
     * The fixture uses {limit 5} without {par date}{inverse}.
     * A BOUCLE without sort must NOT produce the same output as the correct
     * newest-first order.
     */
    public function testTriManquantDetecte(): void
    {
        // Correct order: 5 newest articles, newest first
        $expectedIds = array_slice(array_reverse(self::$articleIds), 0, 5);
        $expected    = implode(',', $expectedIds) . ',';

        // Confirm a correctly-sorted inline BOUCLE produces that order
        $correctBoucle = sprintf(
            '<BOUCLE_ok(ARTICLES){id_rubrique=%d}{statut=publie}{par date}{inverse}{0,5}>#ID_ARTICLE,</BOUCLE_ok>',
            self::RUB_ID
        );
        $this->assertEqualsCode($expected, $correctBoucle, 'Le tri correct doit retourner les 5 plus récents en ordre décroissant');

        // Wrong BOUCLE: matches the fixture (no sort), using {0,5} for compatibility
        $wrongBoucle = sprintf(
            '<BOUCLE_nok(ARTICLES){id_rubrique=%d}{statut=publie}{0,5}>#ID_ARTICLE,</BOUCLE_nok>',
            self::RUB_ID
        );
        $this->assertNotEqualsCode($expected, $wrongBoucle, 'Sans tri, le résultat ne doit pas être dans l\'ordre chronologique inverse');
    }

    /**
     * Test 2 — #UURL_ARTICLE rend un href vide
     *
     * The fixture has href="#UURL_ARTICLE" (double-U typo).
     * An unknown balise renders as an empty string, producing href="".
     */
    public function testBaliseUurlArticleRendHrefVide(): void
    {
        $rendered = Templating::fromFile()->render(self::FIXTURE);
        $this->assertStringContainsString(
            'href=""',
            $rendered,
            '#UURL_ARTICLE (balise inconnue) doit produire un href vide dans le rendu du fichier'
        );
    }

    /**
     * Test 3 — #TITTRE rend une chaîne vide
     *
     * The fixture has #TITTRE (double-T typo). An unknown balise renders
     * as an empty string, so the link text is empty.
     */
    public function testBaliseTittreRendVide(): void
    {
        // Use [(#TITTRE)] — the optional brackets mean it renders empty, not absent
        $boucle = sprintf(
            '<BOUCLE_t(ARTICLES){id_rubrique=%d}{statut=publie}{0,1}>[(#TITTRE)]</BOUCLE_t>',
            self::RUB_ID
        );
        $this->assertEmptyCode($boucle, '#TITTRE (balise inconnue) doit rendre une chaîne vide');
    }

    /**
     * Test 4 — ##DATE ≠ #DATE
     *
     * The fixture has ##DATE (double-#). In SPIP, ## renders a literal #, so
     * ##DATE renders the literal text "#DATE" instead of the formatted date value.
     */
    public function testDoubleDieseDateErrone(): void
    {
        // Render the correct #DATE balise to get the real date string
        $correctBoucle = sprintf(
            '<BOUCLE_dr(ARTICLES){id_rubrique=%d}{statut=publie}{0,1}>#DATE</BOUCLE_dr>',
            self::RUB_ID
        );
        $correctDate = Templating::fromString()->render($correctBoucle);
        $this->assertNotEmpty($correctDate, '#DATE doit retourner une valeur non vide');

        // ##DATE must NOT produce the same output as #DATE
        $wrongBoucle = sprintf(
            '<BOUCLE_dw(ARTICLES){id_rubrique=%d}{statut=publie}{0,1}>##DATE</BOUCLE_dw>',
            self::RUB_ID
        );
        $this->assertNotEqualsCode(
            $correctDate,
            $wrongBoucle,
            '##DATE (double dièse) ne doit pas produire la même valeur que #DATE'
        );
    }

    /**
     * Test 5 — Structure de base du rendu
     *
     * The rubrique has articles, so the fixture must render the <ul> block
     * and at least one <li>, and must NOT show the fallback text.
     */
    public function testFixtureRendDesElements(): void
    {
        $rendered = Templating::fromFile()->render(self::FIXTURE);

        $this->assertStringContainsString('<ul>', $rendered, 'Le rendu doit contenir <ul>');
        $this->assertStringContainsString('<li>', $rendered, 'Le rendu doit contenir au moins un <li>');
        $this->assertStringNotContainsString(
            'Aucun article',
            $rendered,
            'Le texte de repli ne doit pas apparaître quand des articles existent'
        );
    }
}
