<?php
declare(strict_types=1);

use Spip\Test\SquelettesTestCase;
use Spip\Test\Templating;

/**
 * Teste le comportement CORRECT attendu de tests/fixtures/reduireLogoWithError.html.
 * Les tests ÉCHOUENT intentionnellement — phase TDD RED.
 *
 * Erreur dans le fixture (FILTRE_INCORRECT) :
 *   [(#LOGO_RUBRIQUE|image_recadre{300,200})] recadre (crop exact) au lieu de réduire
 *   proportionnellement. Effets :
 *     • upscaling si la source est plus petite que 300×200 → résultat flou
 *     • déformation si le ratio source ≠ 3:2 → rogner change les proportions
 *   Correction attendue : image_reduire{300,200}
 *
 * Chaque test utilise une rubrique distincte (IDs 5–7) afin que les logos
 * de tailles différentes génèrent des entrées de cache-vignettes séparées
 * et n'interfèrent pas entre eux.
 */
final class ReduireLogoWithErrorTest extends SquelettesTestCase
{
    private const FIXTURE = __DIR__ . '/../fixtures/reduireLogoWithError.html';

    /** Logo 100×100 — plus petit que la cible 300×200 (cas upscaling) */
    private const RUB_SMALL = 5;

    /** Logo 400×300 — plus grand, ratio 4:3 différent de la cible 3:2 (cas déformation) */
    private const RUB_LARGE = 6;

    /** Logo 300×200 — dimensions identiques à la cible (cas contrôle) */
    private const RUB_EXACT = 7;

    /** Répertoire absolu IMG/ de l'installation SPIP de test */
    private static string $imgDir = '';

    // -----------------------------------------------------------------------
    // Setup / Teardown
    // -----------------------------------------------------------------------

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        // dirname(__DIR__) = tests/ ; .../vendor/spip/spip/IMG est le répertoire des logos
        self::$imgDir = dirname(__DIR__) . '/../vendor/spip/spip/IMG';

        foreach ([self::RUB_SMALL, self::RUB_LARGE, self::RUB_EXACT] as $id) {
            sql_delete('spip_rubriques', 'id_rubrique=' . $id);
            sql_insertq('spip_rubriques', [
                'id_rubrique' => $id,
                'titre'       => 'Rubrique logo test ' . $id,
                'statut'      => 'publie',
                'lang'        => 'fr',
            ]);
        }

        // Crée les logos immédiatement (tailles fixes par rubrique)
        self::createLogo(self::RUB_SMALL, 100, 100);
        self::createLogo(self::RUB_LARGE, 400, 300);
        self::createLogo(self::RUB_EXACT, 300, 200);
    }

    public static function tearDownAfterClass(): void
    {
        foreach ([self::RUB_SMALL, self::RUB_LARGE, self::RUB_EXACT] as $id) {
            self::removeLogo($id);
            sql_delete('spip_rubriques', 'id_rubrique=' . $id);
        }
        parent::tearDownAfterClass();
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    /**
     * Crée un fichier PNG uni de dimensions $w×$h dans IMG/rubon{$rubId}.png.
     * Naming SPIP : type_du_logo('id_rubrique') = 'rub', mode 'off' → rubon{id}.png
     */
    private static function createLogo(int $rubId, int $w, int $h): void
    {
        $path = self::logoPath($rubId);
        if (file_exists($path)) {
            unlink($path);
        }
        $img = imagecreatetruecolor($w, $h);
        imagefill($img, 0, 0, imagecolorallocate($img, 70, 130, 180));
        imagepng($img, $path);
        imagedestroy($img);
    }

    private static function removeLogo(int $rubId): void
    {
        $path = self::logoPath($rubId);
        if (file_exists($path)) {
            unlink($path);
        }
    }

    private static function logoPath(int $rubId): string
    {
        return self::$imgDir . '/rubon' . $rubId . '.png';
    }

    private function renderForRub(int $rubId): string
    {
        try {
            $raw = Templating::fromString()->render(
                file_get_contents(self::FIXTURE),
                ['id_rubrique' => $rubId]
            );
        } catch (\Spip\Test\Exception\TemplateCompilationErrorException $e) {
            $this->fail('Erreur de compilation : ' . $e->getMessage());
        }
        return (string) preg_replace('/<!--.*?-->/s', '', $raw);
    }

    /**
     * Extrait width et height depuis le premier <img> du HTML rendu.
     * @return array{width: int, height: int}
     */
    private function extractDimensions(string $html): array
    {
        $w = 0;
        $h = 0;
        if (preg_match('/<img[^>]+>/i', $html, $m)) {
            if (preg_match('/\bwidth=["\'](\d+)["\']/', $m[0], $mw)) {
                $w = (int) $mw[1];
            }
            if (preg_match('/\bheight=["\'](\d+)["\']/', $m[0], $mh)) {
                $h = (int) $mh[1];
            }
        }
        return ['width' => $w, 'height' => $h];
    }

    // -----------------------------------------------------------------------
    // Tests
    // -----------------------------------------------------------------------

    /** Diagnostic — vérifie que les pré-requis sont en place avant les vrais tests. */
    public function testDiagnosticLogoEtRendu(): void
    {
        $path = self::logoPath(self::RUB_SMALL);
        $this->assertFileExists($path, "Logo non créé : $path");

        $rub = sql_fetsel('id_rubrique', 'spip_rubriques', 'id_rubrique=' . self::RUB_SMALL);
        $this->assertNotEmpty($rub, 'Rubrique ' . self::RUB_SMALL . ' absente de la base');

        $html = $this->renderForRub(self::RUB_SMALL);
        $this->assertStringContainsString(
            '<img',
            $html,
            'Pas de <img> dans le rendu pour id_rubrique=' . self::RUB_SMALL
            . '. IMG dir=' . self::$imgDir . ' logo=' . $path
        );
    }

    /**
     * Logo 100×100 — source plus petite que la cible 300×200.
     *
     * Correct (image_reduire) : aucun agrandissement ; le logo reste à 100×100.
     * ÉCHOUE car image_recadre upscale le logo à 300×200.
     *
     * image_recadre : scale × max(300/100, 200/100) = 3 → 300×300 → crop → 300×200.
     * image_reduire : source < cible → aucun changement → 100×100.
     */
    public function testPasUpscalingAvecLogoInferieurALaCible(): void
    {
        ['width' => $w, 'height' => $h] = $this->extractDimensions(
            $this->renderForRub(self::RUB_SMALL)
        );

        $this->assertGreaterThan(
            0, $w,
            'Le rendu doit contenir une image (logo rubon' . self::RUB_SMALL . '.png introuvable ?).'
        );
        $this->assertLessThanOrEqual(
            100, $w,
            "Upscaling détecté : logo 100×100 agrandi à {$w}px de large (max attendu : 100px)."
        );
        $this->assertLessThanOrEqual(
            100, $h,
            "Upscaling détecté : logo 100×100 agrandi à {$h}px de haut (max attendu : 100px)."
        );
    }

    /**
     * Logo 400×300 — ratio 4:3, plus grand que la cible 300×200 (ratio 3:2).
     *
     * Correct (image_reduire) : réduit à 267×200 en conservant le ratio 4:3.
     * ÉCHOUE car image_recadre rogne à 300×200 et change le ratio en 3:2.
     *
     * image_recadre : scale 0.75 → 300×225 → crop hauteur → 300×200 (ratio 1.5 ≠ 1.333).
     * image_reduire : scale min(300/400, 200/300) = 0.667 → 267×200 (ratio 1.335 ≈ 1.333).
     */
    public function testRatioPreserveAvecLogoSuperieurALaCible(): void
    {
        ['width' => $w, 'height' => $h] = $this->extractDimensions(
            $this->renderForRub(self::RUB_LARGE)
        );

        $this->assertGreaterThan(
            0, $h,
            'Le rendu doit contenir une image (logo rubon' . self::RUB_LARGE . '.png introuvable ?).'
        );

        // L'image doit tenir dans le cadre 300×200
        $this->assertLessThanOrEqual(300, $w, "Largeur {$w}px dépasse le cadre 300px.");
        $this->assertLessThanOrEqual(200, $h, "Hauteur {$h}px dépasse le cadre 200px.");

        // Le ratio 4:3 ≈ 1.333 doit être conservé (tolérance 1 pixel d'arrondi)
        $ratioSrc = 400 / 300;
        $ratioOut = $w / $h;
        $this->assertEqualsWithDelta(
            $ratioSrc,
            $ratioOut,
            0.02,
            sprintf(
                'Déformation détectée : ratio source 4:3 (%.3f) ≠ ratio rendu %.3f (%d×%d). '
                . 'image_recadre a rogné le logo au lieu de le réduire proportionnellement.',
                $ratioSrc, $ratioOut, $w, $h
            )
        );
    }

    /**
     * Logo 300×200 — dimensions identiques à la cible.
     *
     * Aucune transformation n'est nécessaire : les deux filtres retournent 300×200.
     * Ce test PASSE avec image_recadre ET image_reduire — cas de contrôle.
     */
    public function testLogoAuxDimensionsExactesDeLaCible(): void
    {
        ['width' => $w, 'height' => $h] = $this->extractDimensions(
            $this->renderForRub(self::RUB_EXACT)
        );

        $this->assertGreaterThan(
            0, $w,
            'Le rendu doit contenir une image (logo rubon' . self::RUB_EXACT . '.png introuvable ?).'
        );
        $this->assertSame(300, $w, "Logo 300×200 : largeur attendue 300px, obtenu {$w}px.");
        $this->assertSame(200, $h, "Logo 300×200 : hauteur attendue 200px, obtenu {$h}px.");
    }
}
