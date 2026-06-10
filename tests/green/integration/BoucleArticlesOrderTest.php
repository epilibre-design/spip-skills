<?php
declare(strict_types=1);

use Spip\Test\SquelettesTestCase;

/**
 * Q1 — BOUCLE listing last 5 articles from a rubrique, newest first.
 *
 * Correct:  {id_rubrique=N}{par date}{inverse}{limit 5}
 * Wrong:    {id_rubrique=N}{limit 5}             — missing sort critères
 */
final class BoucleArticlesOrderTest extends SquelettesTestCase
{
    private static int $rubriqueId = 0;
    /** @var int[] */
    private static array $articleIds = [];

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        // Insert a rubrique
        self::$rubriqueId = (int) sql_insertq('spip_rubriques', [
            'titre'  => 'Rubrique test BoucleQ1',
            'statut' => 'publie',
            'lang'   => 'fr',
        ]);

        // Insert 6 articles with strictly ordered dates (oldest → newest)
        $dates = [
            '2020-01-01 00:00:00',
            '2020-03-15 00:00:00',
            '2020-06-30 00:00:00',
            '2021-01-10 00:00:00',
            '2021-08-20 00:00:00',
            '2022-02-14 00:00:00', // newest
        ];
        foreach ($dates as $i => $date) {
            self::$articleIds[] = (int) sql_insertq('spip_articles', [
                'titre'       => 'Article-' . ($i + 1),
                'statut'      => 'publie',
                'id_rubrique' => self::$rubriqueId,
                'date'        => $date,
                'lang'        => 'fr',
            ]);
        }
    }

    public static function tearDownAfterClass(): void
    {
        foreach (self::$articleIds as $id) {
            sql_delete('spip_articles', 'id_article = ' . (int) $id);
        }
        sql_delete('spip_rubriques', 'id_rubrique = ' . self::$rubriqueId);
        parent::tearDownAfterClass();
    }

    /**
     * Correct BOUCLE: {par date}{inverse}{limit 5} must return the 5 newest articles
     * in newest-first order.
     */
    public function testBoucleArticlesOrdreChronologiqueInverse(): void
    {
        // Expected: last 5 IDs in reverse order (newest first)
        $expectedIds = array_slice(array_reverse(self::$articleIds), 0, 5);
        $expected    = implode(',', $expectedIds) . ',';

        $boucle = sprintf(
            '<BOUCLE_arts(ARTICLES){id_rubrique=%d}{par date}{inverse}{0,5}>#ID_ARTICLE,</BOUCLE_arts>',
            self::$rubriqueId
        );

        $this->assertEqualsCode($expected, $boucle);
    }

    /**
     * Wrong BOUCLE: missing {par date}{inverse} must NOT produce newest-first order.
     * This is the intentionally broken version from the Q1 green-test scenario.
     *
     * Note: SquelettesTestCase has no raw-render method; assertNotEqualsCode() is used
     * instead. It renders the code and asserts the result differs from $correctOut.
     */
    public function testBoucleArticlesSansTriEstFausse(): void
    {
        // What the correct template produces
        $correctIds = array_slice(array_reverse(self::$articleIds), 0, 5);
        $correctOut = implode(',', $correctIds) . ',';

        $wrongBoucle = sprintf(
            '<BOUCLE_arts(ARTICLES){id_rubrique=%d}{0,5}>#ID_ARTICLE,</BOUCLE_arts>',
            self::$rubriqueId
        );

        // The wrong template must produce a different result.
        $this->assertNotEqualsCode(
            $correctOut,
            $wrongBoucle,
            [],
            'La BOUCLE sans {par date}{inverse} ne doit pas produire le même ordre que la version correcte.'
        );
    }
}
