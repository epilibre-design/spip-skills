<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Phase TDD RED — fichiers de langue SPIP (skill spip-lang).
 *
 * Les fixtures retournent de simples tableaux PHP (require suffit — pas de SPIP API).
 * Tous les tests ÉCHOUENT intentionnellement.
 *
 * Erreurs documentées dans fixtures/lang/monplugin_fr.php :
 *   ORDRE_NON_ALPHABETIQUE   — clés non triées globalement ('b' avant 'a')
 *   PLACEHOLDER_MAUVAIS_SYNTAXE — placeholder %champ% au lieu de @champ@
 *   PLURIEL_CLE_MANQUANTE    — info_1_objet existe mais info_nb_objets absent
 *
 * Erreur documentée dans fixtures/lang/paquet-monplugin_fr.php :
 *   PAQUET_SLOGAN_MANQUANT   — clé obligatoire monplugin_slogan absente
 */
final class LangFileWithErrorTest extends TestCase
{
    private const FIXTURE_LANG  = __DIR__ . '/../fixtures/lang/monplugin_fr.php';
    private const FIXTURE_PAQUET = __DIR__ . '/../fixtures/lang/paquet-monplugin_fr.php';

    /** @var array<string, string> */
    private static array $strings = [];

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::$strings = require self::FIXTURE_LANG;
    }

    /**
     * ÉCHOUE — ORDRE_NON_ALPHABETIQUE
     * Le tableau place 'bouton_' avant 'aucun_' — ordre non alphabétique.
     * Correction : trier toutes les clés de A à Z avec des commentaires-lettres.
     */
    public function testClesOrdreAlphabetique(): void
    {
        $keys = array_keys(self::$strings);
        $sorted = $keys;
        sort($sorted);
        $this->assertSame(
            $sorted,
            $keys,
            'Les clés du fichier de langue doivent être triées alphabétiquement (ORDRE_NON_ALPHABETIQUE). '
            . 'Ordre actuel : [' . implode(', ', $keys) . ']. '
            . 'Ordre attendu : [' . implode(', ', $sorted) . '].'
        );
    }

    /**
     * ÉCHOUE — PLACEHOLDER_MAUVAIS_SYNTAXE
     * La valeur de 'erreur_champ_vide' contient '%champ%' au lieu de '@champ@'.
     * Correction : remplacer %X% par @X@ dans toutes les valeurs.
     */
    public function testPlaceholderUtiliseArobase(): void
    {
        $invalides = [];
        foreach (self::$strings as $cle => $valeur) {
            if (preg_match('/%\w+%/', $valeur)) {
                $invalides[] = $cle;
            }
        }
        $this->assertSame(
            [],
            $invalides,
            'Les placeholders doivent utiliser @nom@ et non %nom% (PLACEHOLDER_MAUVAIS_SYNTAXE). '
            . 'Clés avec syntaxe incorrecte : [' . implode(', ', $invalides) . '].'
        );
    }

    /**
     * ÉCHOUE — PLURIEL_CLE_MANQUANTE
     * 'info_1_objet' existe mais 'info_nb_objets' est absent.
     * Correction : ajouter 'info_nb_objets' => '@nb@ objets'.
     */
    public function testPairesPlurielsCompletes(): void
    {
        $manquantes = [];
        foreach (array_keys(self::$strings) as $cle) {
            if (preg_match('/^info_1_(.+)$/', $cle, $m)) {
                $pluriel = 'info_nb_' . $m[1] . 's';
                if (!array_key_exists($pluriel, self::$strings)) {
                    $manquantes[] = $pluriel;
                }
            }
        }
        $this->assertSame(
            [],
            $manquantes,
            'Chaque clé info_1_X doit avoir sa paire info_nb_Xs (PLURIEL_CLE_MANQUANTE). '
            . 'Clés manquantes : [' . implode(', ', $manquantes) . '].'
        );
    }

    /**
     * ÉCHOUE — PAQUET_SLOGAN_MANQUANT
     * paquet-monplugin_fr.php ne contient pas 'monplugin_slogan'.
     * Correction : ajouter 'monplugin_slogan' => '...' dans le fichier paquet.
     */
    public function testPaquetContientSlogan(): void
    {
        $paquet = require self::FIXTURE_PAQUET;
        $this->assertArrayHasKey(
            'monplugin_slogan',
            $paquet,
            'Le fichier paquet- doit contenir la clé obligatoire "monplugin_slogan" (PAQUET_SLOGAN_MANQUANT). '
            . 'Clés présentes : [' . implode(', ', array_keys($paquet)) . '].'
        );
    }
}
