<?php
declare(strict_types=1);

use Spip\Test\SquelettesTestCase;
use Spip\Test\Templating;

/**
 * Teste le comportement CORRECT attendu de tests/fixtures/boucleErronnee.html
 * concernant l'affichage conditionnel du message de fallback (erreur #5).
 *
 * Ce test ÉCHOUE intentionnellement car le squelette contient l'erreur #5:
 *   5. <//B_articles> sans balise de fermeture </B_articles> — le texte de fallback
 *      "Aucun article n'est publié dans cette rubrique." est rendu inconditionnellement:
 *      il apparaît même quand des articles publiés existent dans la rubrique.
 *
 * Comportement correct attendu: le message de fallback NE DOIT PAS apparaître
 * quand la rubrique contient des articles publiés.
 *
 * Pour corriger le squelette, il faut ajouter </B_articles> après le texte de fallback:
 *   <//B_articles>
 *   Aucun article n'est publié dans cette rubrique.
 *   </B_articles>
 *
 * Note empirique: sans la balise fermante </B_articles>, SPIP rend le texte après
 * <//B_articles> de façon inconditionnelle (hors du bloc alternatif).
 */
final class BoucleErronneeVideTest extends SquelettesTestCase
{
    private const FIXTURE = __DIR__ . '/../fixtures/boucleErronnee.html';
    private const RUB_ID  = 3;

    /** @var int[] Article IDs inserted for teardown */
    private static array $articleIds = [];

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        // Force-clean rubrique 3 and any existing articles
        sql_delete('spip_articles',  'id_rubrique=' . self::RUB_ID);
        sql_delete('spip_rubriques', 'id_rubrique=' . self::RUB_ID);

        sql_insertq('spip_rubriques', [
            'id_rubrique' => self::RUB_ID,
            'titre'       => 'Rubrique test fallback BoucleErronnee',
            'statut'      => 'publie',
            'lang'        => 'fr',
        ]);

        // Insert published articles so the main block IS rendered
        $articles = [
            ['titre' => 'Article Ancien',  'date' => '2020-01-01 00:00:00'],
            ['titre' => 'Article Recent',  'date' => '2022-02-14 00:00:00'],
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
     * Test 5 — Le message de fallback ne doit PAS apparaître quand des articles sont publiés.
     *
     * ÉCHOUE car <//B_articles> sans balise fermante </B_articles> rend le texte de fallback
     * inconditionnellement: il apparaît même quand la rubrique contient des articles.
     *
     * Le comportement correct: le message "Aucun article..." est réservé au bloc alternatif
     * et ne doit s'afficher que lorsqu'aucun article n'est publié dans la rubrique.
     */
    public function testNAffichePassLeFallbackQuandArticlesPresents(): void
    {
        $rendered = Templating::fromString()->render(file_get_contents(self::FIXTURE));
        $this->assertStringNotContainsString(
            "Aucun article n'est publié dans cette rubrique.",
            $rendered,
            'Le message de fallback ne doit pas apparaître quand des articles sont publiés. '
            . 'Erreur: <//B_articles> sans </B_articles> final rend le texte inconditionnellement.'
        );
    }
}
