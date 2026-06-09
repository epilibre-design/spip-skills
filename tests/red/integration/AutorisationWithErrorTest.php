<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Phase TDD RED — autorisations d'un objet fictif 'monobjet'.
 *
 * Les fonctions autoriser_monobjet_*_dist() sont définies dans la fixture.
 * Tous les tests ÉCHOUENT intentionnellement.
 *
 * Erreurs documentées dans fixtures/autorisations/monobjet_autoriser_with_errors.php :
 *   VOIR_ACCEPTE_ANONYME     — voir() retourne true pour statut vide (anonyme)
 *   MODIFIER_ACCEPTE_BANNI   — modifier() retourne true pour statut '5poubelle'
 *   CREER_EXCLUT_REDACTEUR   — creer() exclut '1comite', retourne false pour un rédacteur
 *   SUPPRIMER_RETOUR_NON_BOOL — supprimer() retourne int au lieu de bool
 */
final class AutorisationWithErrorTest extends TestCase
{
    private const FIXTURE_PHP = __DIR__ . '/../fixtures/autorisations/monobjet_autoriser_with_errors.php';

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        include_once self::FIXTURE_PHP;
    }

    private function qui(string $statut): array
    {
        return ['statut' => $statut, 'id_auteur' => 1, 'webmestre' => 'non', 'restreint' => []];
    }

    /**
     * ÉCHOUE — VOIR_ACCEPTE_ANONYME
     * voir() inclut '' dans in_array, donc un anonyme passe.
     * Correction : retirer '' du tableau.
     */
    public function testVoirRefuseAnonyme(): void
    {
        $qui = $this->qui('');
        $result = autoriser_monobjet_voir_dist('voir', 'monobjet', 0, $qui, []);
        $this->assertFalse(
            $result,
            'voir() doit refuser un anonyme (statut vide) (VOIR_ACCEPTE_ANONYME). '
            . 'La fixture inclut \'\' dans in_array — l\'anonyme est accepté à tort.'
        );
    }

    /**
     * ÉCHOUE — MODIFIER_ACCEPTE_BANNI
     * modifier() inclut '5poubelle' dans in_array, donc un banni passe.
     * Correction : retirer '5poubelle' du tableau.
     */
    public function testModifierRefuseBanni(): void
    {
        $qui = $this->qui('5poubelle');
        $result = autoriser_monobjet_modifier_dist('modifier', 'monobjet', 0, $qui, []);
        $this->assertFalse(
            $result,
            'modifier() doit refuser un banni (statut 5poubelle) (MODIFIER_ACCEPTE_BANNI). '
            . 'La fixture inclut \'5poubelle\' dans in_array — le banni est accepté à tort.'
        );
    }

    /**
     * ÉCHOUE — CREER_EXCLUT_REDACTEUR
     * creer() compare uniquement '0minirezo', exclut '1comite'.
     * Correction : in_array($qui['statut'], ['0minirezo', '1comite']).
     */
    public function testCreerAccepteRedacteur(): void
    {
        $qui = $this->qui('1comite');
        $result = autoriser_monobjet_creer_dist('creer', 'monobjet', 0, $qui, []);
        $this->assertTrue(
            $result,
            'creer() doit accepter un rédacteur (statut 1comite) (CREER_EXCLUT_REDACTEUR). '
            . 'La fixture compare uniquement \'0minirezo\' — le rédacteur est refusé à tort.'
        );
    }

    /**
     * ÉCHOUE — SUPPRIMER_RETOUR_NON_BOOL
     * supprimer() retourne (int) au lieu de bool — viole le contrat autoriser_*_dist().
     * Correction : return $qui['statut'] === '0minirezo' (bool natif).
     */
    public function testSupprimerRetourneBool(): void
    {
        $qui = $this->qui('0minirezo');
        $result = autoriser_monobjet_supprimer_dist('supprimer', 'monobjet', 0, $qui, []);
        $this->assertIsBool(
            $result,
            'supprimer() doit retourner un bool strict (SUPPRIMER_RETOUR_NON_BOOL). '
            . 'La fixture retourne (int) — reçu : ' . gettype($result) . '.'
        );
    }
}
