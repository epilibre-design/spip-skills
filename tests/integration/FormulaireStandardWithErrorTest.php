<?php
declare(strict_types=1);

use Spip\Test\SquelettesTestCase;

/**
 * Vérifie les 7 erreurs intentionnelles dans tests/fixtures/formulaireStandardWithError.html.
 * Tous les tests ÉCHOUENT intentionnellement — phase TDD RED.
 *
 * Erreurs (voir bloc <!--spip-test--> du fixture) :
 *   CLASSE_WRAPPER      — wrapper sans formulaire_spip ni formulaire_editer
 *   CLASSE_MESSAGES     — class="message_ok" / class="alert-success" au lieu de reponse_formulaire_*
 *   SYNTAXE_ENV (×2)    — #ENV sans étoile pour les messages ; #ENV* au lieu de #ENV** pour les erreurs
 *   STRUCTURE_CHAMP     — <ul><li> au lieu de <div class="editer editer_{champ}">
 *   CLASSE_ERREUR_CHAMP — class="champ_erreur" au lieu de class="erreur_message"
 *   CLASSE_SUBMIT       — class="submit-btn" au lieu de class="boutons"
 */
final class FormulaireStandardWithErrorTest extends SquelettesTestCase
{
    private const FIXTURE = __DIR__ . '/../fixtures/formulaireStandardWithError.html';

    private static string $source = '';

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::$source = file_get_contents(self::FIXTURE);
    }

    /**
     * ÉCHOUE — CLASSE_WRAPPER
     * Wrapper sans attribut class : formulaire_spip et formulaire_editer sont absentes.
     * Correction : class="formulaire_spip formulaire_editer formulaire_inscription …"
     */
    public function testClasseWrapper(): void
    {
        $this->assertMatchesRegularExpression(
            '/class="[^"]*\bformulaire_spip\b/',
            self::$source,
            'Le wrapper externe doit avoir la classe formulaire_spip dans son attribut class. '
            . 'Erreur (CLASSE_WRAPPER) : aucune classe présente sur le div wrapper.'
        );
        $this->assertMatchesRegularExpression(
            '/class="[^"]*\bformulaire_editer\b/',
            self::$source,
            'Le wrapper externe doit avoir la classe formulaire_editer dans son attribut class. '
            . 'Erreur (CLASSE_WRAPPER) : aucune classe présente sur le div wrapper.'
        );
    }

    /**
     * ÉCHOUE — CLASSE_MESSAGES
     * Messages globaux dans class="message_ok" et class="alert-success".
     * Correction : class="reponse_formulaire reponse_formulaire_ok"
     * et class="reponse_formulaire reponse_formulaire_erreur".
     */
    public function testClasseMessages(): void
    {
        $this->assertStringNotContainsString(
            'class="message_ok"',
            self::$source,
            'class="message_ok" est incorrect (CLASSE_MESSAGES). '
            . 'Utiliser class="reponse_formulaire reponse_formulaire_ok".'
        );
        $this->assertStringNotContainsString(
            'class="alert-success"',
            self::$source,
            'class="alert-success" est incorrect (CLASSE_MESSAGES). '
            . 'Utiliser class="reponse_formulaire reponse_formulaire_erreur".'
        );
    }

    /**
     * ÉCHOUE — SYNTAXE_ENV (messages globaux)
     * #ENV{message_ok} et #ENV{message_erreur} sans étoile.
     * Correction : #ENV*{message_ok} et #ENV*{message_erreur} (étoile simple).
     */
    public function testSyntaxeEnvMessages(): void
    {
        $this->assertStringNotContainsString(
            '#ENV{message_ok}',
            self::$source,
            '#ENV{message_ok} sans étoile est incorrect (SYNTAXE_ENV). '
            . 'Utiliser #ENV*{message_ok}.'
        );
        $this->assertStringNotContainsString(
            '#ENV{message_erreur}',
            self::$source,
            '#ENV{message_erreur} sans étoile est incorrect (SYNTAXE_ENV). '
            . 'Utiliser #ENV*{message_erreur}.'
        );
    }

    /**
     * ÉCHOUE — STRUCTURE_CHAMP
     * Champs nom et email dans <ul><li> au lieu de <div class="editer editer_{champ}">.
     * Correction : remplacer <ul><li> par <div class="editer editer_nom"> et "editer_email".
     */
    public function testStructureChamp(): void
    {
        $this->assertStringNotContainsString(
            '<ul>',
            self::$source,
            'Ne pas utiliser <ul> pour les champs (STRUCTURE_CHAMP). '
            . 'Utiliser <div class="editer editer_champ">.'
        );
        $this->assertMatchesRegularExpression(
            '/class="editer editer_nom\b/',
            self::$source,
            'Le champ nom doit être dans <div class="editer editer_nom …"> (STRUCTURE_CHAMP). '
            . 'Erreur : structure <ul><li> utilisée.'
        );
        $this->assertMatchesRegularExpression(
            '/class="editer editer_email\b/',
            self::$source,
            'Le champ email doit être dans <div class="editer editer_email …"> (STRUCTURE_CHAMP). '
            . 'Erreur : structure <ul><li> utilisée.'
        );
    }

    /**
     * ÉCHOUE — SYNTAXE_ENV (erreur du champ nom)
     * (#ENV*{erreurs}|table_valeur{nom}) avec étoile simple.
     * Correction : (#ENV**{erreurs}|table_valeur{nom}) avec double étoile.
     */
    public function testSyntaxeEnvErreursChamp(): void
    {
        $this->assertMatchesRegularExpression(
            '/\(#ENV\*\*\{erreurs\}\|table_valeur\{nom\}\)/',
            self::$source,
            'L\'erreur du champ nom doit utiliser (#ENV**{erreurs}|table_valeur{nom}) '
            . '(double étoile) (SYNTAXE_ENV). Erreur : #ENV* (étoile simple) est utilisé.'
        );
        $this->assertMatchesRegularExpression(
            '/\(#ENV\*\*\{erreurs\}\|table_valeur\{email\}\)/',
            self::$source,
            'L\'erreur du champ email doit utiliser (#ENV**{erreurs}|table_valeur{email}) '
            . '(double étoile) (SYNTAXE_ENV). Erreur : span d\'erreur absent pour email.'
        );
    }

    /**
     * ÉCHOUE — CLASSE_ERREUR_CHAMP
     * Texte d'erreur dans class="champ_erreur".
     * Correction : class="erreur_message".
     */
    public function testClasseErreurChamp(): void
    {
        $this->assertStringNotContainsString(
            'class="champ_erreur"',
            self::$source,
            'class="champ_erreur" est incorrect (CLASSE_ERREUR_CHAMP). '
            . 'Utiliser class="erreur_message".'
        );
        $this->assertStringContainsString(
            'class="erreur_message"',
            self::$source,
            'Les textes d\'erreur de champ doivent utiliser class="erreur_message" '
            . '(CLASSE_ERREUR_CHAMP). Erreur : class="champ_erreur" est utilisé.'
        );
    }

    /**
     * ÉCHOUE — CLASSE_SUBMIT
     * Bouton de soumission dans class="submit-btn".
     * Correction : <p class="boutons">.
     */
    public function testClasseSubmit(): void
    {
        $this->assertStringNotContainsString(
            'class="submit-btn"',
            self::$source,
            'class="submit-btn" est incorrect (CLASSE_SUBMIT). '
            . 'Utiliser <p class="boutons">.'
        );
        $this->assertStringContainsString(
            'class="boutons"',
            self::$source,
            'Le bouton de soumission doit être dans un élément avec class="boutons" '
            . '(CLASSE_SUBMIT). Erreur : class="submit-btn" est utilisé.'
        );
    }
}
