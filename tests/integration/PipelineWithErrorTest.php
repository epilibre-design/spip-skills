<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Phase TDD RED — handlers de pipeline avec 3 erreurs intentionnelles.
 *
 * Tous les tests ECHOUENT intentionnellement.
 *
 * Erreurs documentees dans fixtures/pipelines/monplugin_pipelines_with_errors.php :
 *   INSERT_HEAD_RETOUR_ARRAY  — text pipeline retourne un array au lieu d'une string
 *   POST_EDITION_FLUX_NULL    — array pipeline oublie return $flux, retourne null
 *   POST_EDITION_DATA_ECRASE  — array pipeline ecrase $flux['data'] au lieu de fusionner
 */
final class PipelineWithErrorTest extends TestCase
{
    private const FIXTURE_PHP = __DIR__ . '/../fixtures/pipelines/monplugin_pipelines_with_errors.php';

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        include_once self::FIXTURE_PHP;
    }

    /**
     * ECHOUE — INSERT_HEAD_RETOUR_ARRAY
     * monplugin_insert_head_with_errors() retourne un array au lieu d'une string.
     * Correction : return $texte . '<link ...>' (concatenation).
     */
    public function testInsertHeadRetourneString(): void
    {
        $result = monplugin_insert_head_with_errors('');
        $this->assertIsString(
            $result,
            'Un text pipeline doit retourner une string (INSERT_HEAD_RETOUR_ARRAY). '
            . 'Recu : ' . gettype($result) . '.'
        );
    }

    /**
     * ECHOUE — POST_EDITION_FLUX_NULL
     * monplugin_post_edition_sans_retour() n'a pas de return — retourne null.
     * Correction : ajouter return $flux a la fin de la fonction.
     */
    public function testPostEditionRetourneFlux(): void
    {
        $flux = ['args' => ['objet' => 'article', 'id_objet' => 1], 'data' => []];
        $result = monplugin_post_edition_sans_retour($flux);
        $this->assertIsArray(
            $result,
            'Un array pipeline doit retourner $flux (POST_EDITION_FLUX_NULL). '
            . 'Recu : ' . gettype($result) . '. La chaine est coupee pour tous les handlers suivants.'
        );
    }

    /**
     * ECHOUE — POST_EDITION_DATA_ECRASE
     * monplugin_post_edition_ecrase_data() remplace $flux['data'] entier.
     * Les cles deja presentes (ex. 'titre') sont perdues.
     * Correction : $flux['data'] = array_merge($flux['data'], $nouveaux).
     */
    public function testPostEditionPreserveDataOriginale(): void
    {
        $flux = [
            'args' => ['objet' => 'article', 'id_objet' => 1],
            'data' => ['titre' => 'Mon titre', 'texte' => 'Contenu existant'],
        ];
        $result = monplugin_post_edition_ecrase_data($flux);
        $this->assertArrayHasKey(
            'titre',
            $result['data'],
            'Un array pipeline ne doit pas ecraser $flux[\'data\'] (POST_EDITION_DATA_ECRASE). '
            . 'La cle "titre" doit etre preservee. '
            . 'Cles presentes apres le handler : [' . implode(', ', array_keys($result['data'])) . '].'
        );
    }
}
