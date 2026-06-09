<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Phase TDD RED — formulaire CVT d'inscription complet.
 *
 * Le template HTML (inscription_with_errors.html) est canonique et correct.
 * Les erreurs sont dans le PHP CVT (charger/verifier/traiter).
 * Tous les tests ÉCHOUENT intentionnellement.
 *
 * Erreurs documentées dans fixtures/formulaires/inscription_with_errors.php :
 *   CHARGER_EMAIL_ABSENT    — charger() ne retourne pas la clé 'email'
 *   VERIFIER_NOM_IGNORE     — verifier() n'exige pas que 'nom' soit renseigné
 *   VERIFIER_CLE_EMAIL      — verifier() place l'erreur email sous 'mail' au lieu de 'email'
 *   TRAITER_PAS_UTILISATEUR — traiter() n'insère pas dans spip_auteurs
 *   TRAITER_SANS_MESSAGE_OK — traiter() ne retourne pas 'message_ok'
 */
final class FormulaireInscriptionWithErrorTest extends TestCase
{
    private const FIXTURE_PHP = __DIR__ . '/../fixtures/formulaires/inscription_with_errors.php';

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        include_once self::FIXTURE_PHP;
    }

    protected function tearDown(): void
    {
        $_POST = [];
    }

    /**
     * ÉCHOUE — CHARGER_EMAIL_ABSENT
     * charger() retourne ['nom' => ''] sans la clé 'email'.
     * Correction : retourner ['nom' => '', 'email' => ''].
     */
    public function testChargerRetourneEmail(): void
    {
        $valeurs = formulaires_inscription_with_errors_charger_dist();
        $this->assertIsArray($valeurs);
        $this->assertArrayHasKey(
            'email',
            $valeurs,
            'charger() doit retourner la clé "email" (CHARGER_EMAIL_ABSENT). '
            . 'Reçu : [' . implode(', ', array_keys($valeurs)) . ']'
        );
    }

    /**
     * ÉCHOUE — VERIFIER_NOM_IGNORE
     * verifier() retourne [] même quand 'nom' est vide.
     * Correction : $erreurs['nom'] = '...' si _request('nom') est vide.
     */
    public function testVerifierExigeNomObligatoire(): void
    {
        $_POST['nom'] = '';
        $_POST['email'] = 'valide@exemple.fr';
        $erreurs = formulaires_inscription_with_errors_verifier_dist();
        $this->assertArrayHasKey(
            'nom',
            $erreurs,
            'verifier() doit retourner une erreur pour "nom" vide (VERIFIER_NOM_IGNORE).'
        );
    }

    /**
     * ÉCHOUE — VERIFIER_CLE_EMAIL
     * verifier() place l'erreur email invalide sous la clé 'mail' au lieu de 'email'.
     * Correction : utiliser $erreurs['email'] = '...'.
     */
    public function testVerifierRemplitCleEmail(): void
    {
        $_POST['nom'] = 'Alice';
        $_POST['email'] = 'pas-un-email';
        $erreurs = formulaires_inscription_with_errors_verifier_dist();
        $this->assertArrayHasKey(
            'email',
            $erreurs,
            'verifier() doit placer l\'erreur de format sous la clé "email" '
            . '(VERIFIER_CLE_EMAIL). La clé "mail" est incorrecte.'
        );
    }

    /**
     * ÉCHOUE — TRAITER_SANS_MESSAGE_OK
     * traiter() retourne [] sans la clé 'message_ok'.
     * Correction : retourner ['message_ok' => '...'] après insertion réussie.
     */
    public function testTraiterRetourneMessageOk(): void
    {
        $_POST['nom'] = 'Alice';
        $_POST['email'] = 'alice@exemple.fr';
        $result = formulaires_inscription_with_errors_traiter_dist();
        $this->assertArrayHasKey(
            'message_ok',
            $result,
            'traiter() doit retourner "message_ok" après une inscription valide '
            . '(TRAITER_SANS_MESSAGE_OK).'
        );
    }

    /**
     * ÉCHOUE — TRAITER_PAS_UTILISATEUR
     * traiter() ne crée pas d'entrée dans spip_auteurs.
     * Correction : sql_insertq('spip_auteurs', [...]) avec les données saisies.
     */
    public function testTraiterCreeUtilisateur(): void
    {
        $_POST['nom'] = 'Alice';
        $_POST['email'] = 'alice@exemple.fr';
        $before = (int) sql_countsel('spip_auteurs', '1=1');
        formulaires_inscription_with_errors_traiter_dist();
        $after = (int) sql_countsel('spip_auteurs', '1=1');
        $this->assertGreaterThan(
            $before,
            $after,
            'traiter() doit insérer un auteur dans spip_auteurs (TRAITER_PAS_UTILISATEUR).'
        );
    }
}
