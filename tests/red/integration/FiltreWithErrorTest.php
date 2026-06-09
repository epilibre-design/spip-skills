<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Phase TDD RED — filtre de troncature avec 3 erreurs intentionnelles.
 *
 * Tous les tests ECHOUENT intentionnellement.
 *
 * Erreurs documentees dans fixtures/filtres/filtre_tronquer_with_errors.php :
 *   RETOUR_NULL_SUR_VIDE  — filtre() retourne null pour une entree vide au lieu de ''
 *   LONGUEUR_IGNOREE      — filtre() ignore le parametre $longueur, tronque toujours a 100
 *   HTML_NON_ECHAPPE      — filtre() retourne le HTML brut sans htmlspecialchars()
 */
final class FiltreWithErrorTest extends TestCase
{
    private const FIXTURE_PHP = __DIR__ . '/../fixtures/filtres/filtre_tronquer_with_errors.php';

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        include_once self::FIXTURE_PHP;
    }

    /**
     * ECHOUE — RETOUR_NULL_SUR_VIDE
     * filtre_tronquer_with_errors('') retourne null au lieu de ''.
     * Correction : return '' quand $texte est vide.
     */
    public function testRetourneStringPourVide(): void
    {
        $result = filtre_tronquer_with_errors('');
        $this->assertIsString(
            $result,
            'filtre_tronquer_with_errors(\'\') doit retourner une string (RETOUR_NULL_SUR_VIDE). '
            . 'Recu : ' . gettype($result) . '.'
        );
    }

    /**
     * ECHOUE — LONGUEUR_IGNOREE
     * filtre_tronquer_with_errors($texte, 20) tronque toujours a 100 caracteres.
     * Correction : utiliser mb_substr($texte, 0, $longueur).
     */
    public function testRespecteLongueur(): void
    {
        $texte = str_repeat('a', 200);
        $result = filtre_tronquer_with_errors($texte, 20);
        $this->assertTrue(
            mb_strlen((string) $result) <= 20,
            'filtre_tronquer_with_errors($texte, 20) doit retourner au plus 20 caracteres (LONGUEUR_IGNOREE). '
            . 'Longueur recue : ' . mb_strlen((string) $result) . '.'
        );
    }

    /**
     * ECHOUE — HTML_NON_ECHAPPE
     * filtre_tronquer_with_errors('<b>test</b>') retourne le HTML brut.
     * Correction : appliquer htmlspecialchars(..., ENT_QUOTES, 'UTF-8').
     */
    public function testEchappe_HTML(): void
    {
        $result = filtre_tronquer_with_errors('<b>test</b>');
        $this->assertStringNotContainsString(
            '<',
            (string) $result,
            'filtre_tronquer_with_errors() doit echapper les balises HTML (HTML_NON_ECHAPPE). '
            . 'Le caractere "<" ne doit pas apparaitre dans le retour.'
        );
    }
}
