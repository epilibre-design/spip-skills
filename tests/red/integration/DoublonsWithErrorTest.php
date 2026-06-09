<?php
declare(strict_types=1);

use Spip\Test\SquelettesTestCase;
use Spip\Test\Templating;

/**
 * Teste le comportement CORRECT attendu de tests/fixtures/doublonsWithError.html.
 * Les tests ÉCHOUENT intentionnellement — phase TDD RED.
 *
 * Erreurs dans le fixture (rubrique 30 avec 7 articles publiés, par date inverse) :
 *   1. DOUBLONS_MANQUANT : BOUCLE_reste sans {doublons} → l'article vedette (A1)
 *      réapparaît en tête de la liste secondaire.
 *   2. OFFSET_INCORRECT : {0,5} au lieu de {1,5} ou {doublons}{0,5} → la liste
 *      secondaire commence au même article que la vedette et écarte le 6ème article (A6).
 *
 * Avec le fixture corrigé ({doublons} + {0,5} ou {1,5}) :
 *   - vedette : A1 (le plus récent)
 *   - liste   : A2, A3, A4, A5, A6 (les 5 articles suivants, sans doublons)
 */
final class DoublonsWithErrorTest extends SquelettesTestCase
{
    private const FIXTURE = __DIR__ . '/../fixtures/doublonsWithError.html';
    private const RUB_ID  = 30;

    /** Titres des articles, index 0 = plus récent (vedette), index 6 = plus ancien */
    private static array $titres = [];
    private static array $articleIds = [];

    // -----------------------------------------------------------------------
    // Setup / Teardown
    // -----------------------------------------------------------------------

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        sql_delete('spip_articles',  'id_rubrique=' . self::RUB_ID);
        sql_delete('spip_rubriques', 'id_rubrique=' . self::RUB_ID);

        sql_insertq('spip_rubriques', [
            'id_rubrique' => self::RUB_ID,
            'titre'       => 'Rubrique doublons test',
            'statut'      => 'publie',
            'lang'        => 'fr',
        ]);

        // 7 articles : A1 (le plus récent = vedette) → A7 (le plus ancien)
        // Liste restante correcte : A2–A6 (5 articles après la vedette)
        $dates = [
            '2024-07-01 00:00:00', // A1 — vedette
            '2024-06-01 00:00:00', // A2 — 1er article restant
            '2024-05-01 00:00:00', // A3
            '2024-04-01 00:00:00', // A4
            '2024-03-01 00:00:00', // A5
            '2024-02-01 00:00:00', // A6 — 5ème article restant
            '2024-01-01 00:00:00', // A7 — hors limite (ne doit pas apparaître)
        ];

        foreach ($dates as $i => $date) {
            $titre = 'Article-' . ($i + 1);
            self::$titres[] = $titre;
            self::$articleIds[] = (int) sql_insertq('spip_articles', [
                'titre'       => $titre,
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

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function render(): string
    {
        try {
            $raw = Templating::fromString()->render(
                file_get_contents(self::FIXTURE),
                ['id_rubrique' => self::RUB_ID]
            );
        } catch (\Spip\Test\Exception\TemplateCompilationErrorException $e) {
            $this->fail('Erreur de compilation : ' . $e->getMessage());
        }
        return (string) preg_replace('/<!--.*?-->/s', '', $raw);
    }

    private function extractFeatured(string $html): string
    {
        preg_match('/<article class="featured">.*?<\/article>/s', $html, $m);
        return $m[0] ?? '';
    }

    private function extractListe(string $html): string
    {
        preg_match('/<ul class="liste-restante">.*?<\/ul>/s', $html, $m);
        return $m[0] ?? '';
    }

    // -----------------------------------------------------------------------
    // Tests
    // -----------------------------------------------------------------------

    /** Diagnostic — l'article le plus récent (A1) apparaît bien dans la zone vedette. */
    public function testDiagnosticVedetteEstLeArticleLePlusRecent(): void
    {
        $html     = $this->render();
        $featured = $this->extractFeatured($html);

        $this->assertNotEmpty($featured, 'La zone <article class="featured"> doit être présente.');
        $this->assertStringContainsString(
            self::$titres[0],
            $featured,
            'La vedette doit contenir l\'article le plus récent (' . self::$titres[0] . ').'
        );
    }

    /**
     * L'article vedette (A1) ne doit PAS réapparaître dans <ul class="liste-restante">.
     * ÉCHOUE car BOUCLE_reste n'a pas {doublons} → A1 figure en tête de liste.
     */
    public function testVedetteAbsenteDeLaListe(): void
    {
        $liste = $this->extractListe($this->render());

        $this->assertNotEmpty($liste, 'La liste <ul class="liste-restante"> doit être présente.');
        $this->assertStringNotContainsString(
            self::$titres[0],
            $liste,
            'L\'article vedette "' . self::$titres[0] . '" ne doit pas réapparaître dans la liste. '
            . 'Erreur : {doublons} absent de BOUCLE_reste.'
        );
    }

    /**
     * Le 6ème article le plus récent (A6) doit figurer dans la liste restante.
     * ÉCHOUE car {0,5} sans {doublons} retourne A1–A5 au lieu de A2–A6.
     */
    public function testListeResteContientLeSixiemeArticle(): void
    {
        $liste = $this->extractListe($this->render());

        $this->assertNotEmpty($liste, 'La liste <ul class="liste-restante"> doit être présente.');
        $this->assertStringContainsString(
            self::$titres[5],
            $liste,
            'Le 6ème article "' . self::$titres[5] . '" doit figurer dans la liste restante. '
            . 'Erreur : {0,5} sans {doublons} retourne les articles 1–5 au lieu de 2–6.'
        );
    }
}
