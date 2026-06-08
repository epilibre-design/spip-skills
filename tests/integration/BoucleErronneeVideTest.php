<?php
declare(strict_types=1);

use Spip\Test\SquelettesTestCase;
use Spip\Test\Templating;

/**
 * Teste le comportement CORRECT attendu de tests/fixtures/boucleErronnee.html
 * lorsque la rubrique 3 ne contient aucun article publié.
 *
 * Ce test ÉCHOUE intentionnellement car le squelette contient l'erreur #5:
 *   5. <//B_articles> est positionné avant le texte de fallback sans balise de fermeture
 *      </B_articles> → "Aucun article n'est publié dans cette rubrique." n'apparaît pas
 *      quand la rubrique est vide.
 *
 * Pour corriger le squelette, il faut ajouter </B_articles> après le texte de fallback:
 *   <//B_articles>
 *   Aucun article n'est publié dans cette rubrique.
 *   </B_articles>
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
            'titre'       => 'Rubrique test vide BoucleErronnee',
            'statut'      => 'publie',
            'lang'        => 'fr',
        ]);

        // Insert only one article with statut='prepa' (not published) — rubrique appears empty
        self::$articleIds[] = (int) sql_insertq('spip_articles', [
            'titre'       => 'Article Brouillon',
            'statut'      => 'prepa',
            'id_rubrique' => self::RUB_ID,
            'date'        => '2022-01-01 00:00:00',
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
     * Test 5 — Le message de fallback doit apparaître quand la rubrique n'a aucun article publié.
     *
     * ÉCHOUE car <//B_articles> est mal positionné dans le squelette:
     * le texte "Aucun article..." se trouve en dehors du bloc alternatif
     * (la balise fermante </B_articles> est manquante après le texte de fallback),
     * ce qui empêche le message d'être affiché correctement quand la rubrique est vide.
     */
    public function testAfficheMessageFallbackQuandRubriqueVide(): void
    {
        $rendered = Templating::fromString()->render(file_get_contents(self::FIXTURE));
        $this->assertStringContainsString(
            "Aucun article n'est publié dans cette rubrique.",
            $rendered,
            'Le message de fallback doit apparaître quand aucun article n\'est publié. '
            . 'Erreur: <//B_articles> est mal positionné (balise fermante </B_articles> manquante après le texte fallback).'
        );
    }
}
