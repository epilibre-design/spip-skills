<?php
declare(strict_types=1);

use Spip\Test\SquelettesTestCase;
use Spip\Test\Templating;

/**
 * Teste le comportement CORRECT attendu de tests/fixtures/reduireLogo.html.
 *
 * Chaque test utilise une rubrique distincte (IDs 5–7) afin que les logos
 * de tailles différentes génèrent des entrées de cache-vignettes séparées
 * et n'interfèrent pas entre eux.
 */
final class ReduireLogoTest extends SquelettesTestCase
{
    private const FIXTURE = __DIR__ . '/../fixtures/reduireLogo.html';

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
        self::$imgDir = dirname(__DIR__) . '/vendor/spip/spip/IMG';

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
     * Naming SPIP : type_du_logo('id_rubrique') = 'rub', mode 'on' → rubon{id}.png
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

    /**
     * Logo 100×100 — source plus petite que la cible 300×200.
     * image_reduire : source < cible → aucun changement → 100×100. Pas d'upscaling.
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
     * image_reduire : scale min(300/400, 200/300) = 0.667 → 267×200 (ratio 4:3 conservé).
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
     * image_reduire : aucune transformation nécessaire → reste 300×200.
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
