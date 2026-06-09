<?php
declare(strict_types=1);

use Spip\Test\SquelettesTestCase;
use Spip\Test\Templating;

/**
 * Teste le comportement CORRECT attendu de tests/fixtures/recursiveBOUCLEWithError.html.
 * Les tests ÉCHOUENT intentionnellement — phase TDD RED.
 *
 * Erreur dans le fixture (LISTE_NON_STRUCTUREE) :
 *   </B_rubs> se ferme immédiatement après <ul>, avant la boucle.
 *   SPIP ne peut pas associer <B_rubs>/<BB_rubs> avec <BOUCLE_rubs> → ces marqueurs
 *   de section apparaissent en clair dans le rendu au lieu d'être compilés en PHP.
 *   De plus, </ul> dans <BB_rubs> est toujours rendu → balises <ul> déséquilibrées.
 *
 *   Correction (boucles.md §Recursive BOUCLEs) :
 *     <B_rubs>
 *     <ul>
 *     <BOUCLE_rubs(RUBRIQUES){id_parent}{par num titre, titre}>
 *       <li>...</li>
 *     </BOUCLE_rubs>
 *     </ul>
 *     </B_rubs>
 */
final class RecursiveBOUCLEWithErrorTest extends SquelettesTestCase
{
    private const FIXTURE = __DIR__ . '/../fixtures/recursiveBOUCLEWithError.html';

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function render(): string
    {
        try {
            $raw = Templating::fromString()->render(
                file_get_contents(self::FIXTURE),
                []
            );
        } catch (\Spip\Test\Exception\TemplateCompilationErrorException $e) {
            $this->fail('Erreur de compilation : ' . $e->getMessage());
        }
        return (string) preg_replace('/<!--.*?-->/s', '', $raw);
    }

    // -----------------------------------------------------------------------
    // Tests
    // -----------------------------------------------------------------------

    /**
     * Diagnostic — le fixture se compile et produit du HTML sans exception.
     * Les marqueurs SPIP mal positionnés sont visibles dans la sortie (voir tests suivants).
     */
    public function testDiagnosticCompileSansException(): void
    {
        $html = $this->render();
        // On ne vérifie pas le contenu ici — la structure est cassée mais la
        // compilation ne lève pas d'exception. Les vrais problèmes sont dans les tests suivants.
        $this->assertIsString($html, 'Le rendu doit retourner une chaîne.');
    }

    /**
     * Le rendu ne doit pas contenir les marqueurs de section SPIP en clair.
     * ÉCHOUE car </B_rubs> ferme avant la boucle → SPIP ne peut pas associer
     * les sections avec la BOUCLE_rubs → les marqueurs s'affichent en texte brut.
     */
    public function testRenduSansMarqueursSectionSpip(): void
    {
        $html = $this->render();

        $this->assertStringNotContainsString(
            '</B_rubs>',
            $html,
            'Le marqueur </B_rubs> ne doit pas apparaître dans le rendu. '
            . 'Erreur : </B_rubs> se ferme avant <BOUCLE_rubs>, SPIP ne peut pas compiler la section.'
        );

        $this->assertStringNotContainsString(
            '<BB_rubs>',
            $html,
            'Le marqueur <BB_rubs> ne doit pas apparaître dans le rendu. '
            . 'Erreur : <BB_rubs> non associé à la boucle, rendu en texte brut.'
        );
    }

    /**
     * Les balises <ul> et </ul> doivent être équilibrées dans le rendu.
     * ÉCHOUE car <BB_rubs> produit </ul> toujours, et la boucle récursive
     * via <BB_sous> ajoute un </ul> supplémentaire pour chaque feuille sans enfants.
     */
    public function testBalisesFermetureUlEquilibrees(): void
    {
        $html  = $this->render();
        $open  = substr_count($html, '<ul>');
        $close = substr_count($html, '</ul>');
        $this->assertSame(
            $open,
            $close,
            sprintf(
                'Balises <ul> déséquilibrées : %d ouvertures vs %d fermetures. '
                . 'Correction : placer </ul> dans le post-section de <B_rubs>...</B_rubs>.',
                $open,
                $close
            )
        );
    }
}
