<?php
declare(strict_types=1);

use Spip\Test\SquelettesTestCase;
use Spip\Test\Templating;

/**
 * Teste le comportement CORRECT attendu de tests/fixtures/paginationWithError.html.
 * Tous les tests ÉCHOUENT intentionnellement — phase TDD RED.
 *
 * Erreurs dans le fixture (rubrique 4 avec 7 articles publiés) :
 *   1. {pagination 10} au lieu de {pagination 5} → tous les articles sur une page
 *   2. <li> sans <ul> parent → HTML invalide
 *   3. #PAGINATION après </B_arts> → erreur compilateur zbug_champ_hors_boucle
 */
final class PaginationWithErrorTest extends SquelettesTestCase
{
    private const FIXTURE = __DIR__ . '/../fixtures/paginationWithError.html';
    private const RUB_ID  = 4;

    private static array $articleIds = [];

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        sql_delete('spip_articles',  'id_rubrique=' . self::RUB_ID);
        sql_delete('spip_rubriques', 'id_rubrique=' . self::RUB_ID);

        sql_insertq('spip_rubriques', [
            'id_rubrique' => self::RUB_ID,
            'titre'       => 'Rubrique pagination WithError',
            'statut'      => 'publie',
            'lang'        => 'fr',
        ]);

        $dates = [
            '2020-01-01 00:00:00',
            '2020-06-01 00:00:00',
            '2021-01-01 00:00:00',
            '2021-06-01 00:00:00',
            '2022-01-01 00:00:00',
            '2022-06-01 00:00:00',
            '2023-01-01 00:00:00',
        ];
        foreach ($dates as $i => $date) {
            self::$articleIds[] = (int) sql_insertq('spip_articles', [
                'titre'       => 'Article ' . ($i + 1),
                'statut'      => 'publie',
                'id_rubrique' => self::RUB_ID,
                'date'        => $date,
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
        try {
            $raw = Templating::fromString()->render(
                file_get_contents(self::FIXTURE),
                ['id_rubrique' => self::RUB_ID]
            );
        } catch (\Spip\Test\Exception\TemplateCompilationErrorException $e) {
            $this->fail('Erreur de compilation — le rendu du fixture a échoué : ' . $e->getMessage());
        }
        // Strip HTML comments so the <!--spip-test YAML block doesn't contaminate assertions
        return (string) preg_replace('/<!--.*?-->/s', '', $raw);
    }

    /**
     * Erreur 1 — {pagination 10} au lieu de {pagination 5}.
     * Avec 7 articles, la 1ère page doit afficher 5 articles.
     * ÉCHOUE car {pagination 10} affiche les 7 articles sur une seule page.
     */
    public function testPaginationLimiteAcinqArticlesParPage(): void
    {
        $this->assertSame(
            5,
            substr_count($this->render(), '<li><a href'),
            'La 1ère page doit contenir exactement 5 articles. Erreur : {pagination 10} affiche tous les articles.'
        );
    }

    /**
     * Erreur 2 — <li> sans <ul> parent.
     * La liste d'articles DOIT être encadrée par <ul>.
     * ÉCHOUE car le fixture n'a pas de balise <ul>.
     */
    public function testListeEstStructureeAvecUl(): void
    {
        $this->assertStringContainsString(
            '<ul>',
            $this->render(),
            'La liste doit être encadrée par <ul>. Erreur : <ul> absent dans le fixture.'
        );
    }

    /**
     * Erreur 3 — #PAGINATION placé après </B_arts> (hors boucle).
     * Correct : #PAGINATION dans <B_arts>...</B_arts> génère des liens de navigation.
     * ÉCHOUE (via TemplateCompilationErrorException au 1er run, ou via absence de debut_arts=
     * au 2e run sur le cache compilé) tant que #PAGINATION reste hors de la boucle.
     */
    public function testPaginationHorsBoucleDeclencheErreurCompilateur(): void
    {
        try {
            $rendered = (string) preg_replace(
                '/<!--.*?-->/s',
                '',
                Templating::fromString()->render(
                    file_get_contents(self::FIXTURE),
                    ['id_rubrique' => self::RUB_ID]
                )
            );
        } catch (\Spip\Test\Exception\TemplateCompilationErrorException $e) {
            $this->fail(
                '#PAGINATION hors boucle déclenche zbug_champ_hors_boucle. '
                . 'Déplacer [(#PAGINATION)] dans <B_arts>...</B_arts>. '
                . 'Erreur : ' . $e->getMessage()
            );
        }

        $this->assertStringContainsString(
            'debut_arts=',
            $rendered,
            '#PAGINATION dans <B_arts> doit générer des liens de navigation entre les pages (debut_arts=).'
        );
    }
}
