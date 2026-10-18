<?php

declare(strict_types=1);

namespace Spip;

/**
 * Version logicielle SPIP.
 */
final class SpipVersion
{
	/**
	 * @var string Numero de version de SPIP
	 *
	 * Utilisé par les plugins pour spécifier les versions de SPIP nécessaires
	 *
	 * S'en tenir à du semver.
	 * Ex: 2.0.0, 2.0.0-dev, 2.0.0-beta, 2.0.0-beta2
	 */
	public const string VERSION = '5.0.0-beta';

	public const int ID = 50000;

	/**
	 * @var int Date de la dernière modification cassant la compatibilité et/ou nécessitant un recalcul des squelettes
	 *
	 * Ex: 2026_03_06
	 */
	public const int CODE = 2026_09_22;

	/**
	 * @var int Date de mise à jour la structure de base SQL
	 *
	 * Date + numero incremental a 2 chiffres YYYYMMDDXX)
	 *
	 * Ex: 2022_02_23_03, 2022_02_23_04, 2022_02_23_05
	 */
	public const int BASE = 2026_08_03_00;

	public const string PHP_MIN = '8.4.0';

	public const string PHP_MAX = '8.5.99';

	private function __construct() {
	}

	public static function display(): string {
		return self::VERSION;
	}

	public static function branch(): string {
		if (preg_match('/^(\d+)\.(\d+)/', self::VERSION, $matches)) {
			return $matches[1] . '.' . $matches[2];
		}

		throw new \UnexpectedValueException('Version invalide');
	}
}
