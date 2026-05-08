# SPIP coding standards (SCS1) - local reference

This page is a local, agent-friendly reference for SPIP coding rules.
It avoids external crawling during generation.

Scope: SPIP Coding Standard 1 (SCS1), aligned with PSR-12 in spirit, with SPIP-specific differences.

Validation note: SCS1 is the style target. Automatic enforcement depends on the active toolchain.
In many projects, the analysis stack is EasyCodingStandard (ECS) combining PHP-CS-Fixer rules
and selected PHP_CodeSniffer sniffs.

## Primary rules

### Files

- Use `<?php` only. Never use short open tags `<?`.
- Use UTF-8 without BOM.
- Use Unix line endings (LF).
- End files with one trailing LF.
- Omit closing `?>` in PHP-only files.

### Lines and indentation

- No strict hard line length.
- Soft limit: 120 characters.
- Recommended readability target: around 80 characters when practical.
- No trailing whitespace.
- One statement per line.
- Indentation uses tabs (not spaces), unlike strict PSR-12.

### Functions, classes, namespaces

- SPIP 4.x legacy code is not PSR-4-autoload based by default.
- Function names should use snake_case.
- Opening brace for functions stays on the same line, with one space before `{`.

Example:

```php
function ma_fonction() {
	$ma_variable = 0;

	return $ma_variable;
}
```

### Constants, variables, globals

- Constants use uppercase with underscores, prefixed with `_`.
- Variables and function arguments should use snake_case.
- Prefer `$GLOBALS['x']` over `global $x` for explicitness in SPIP code.

Examples:

```php
define('_NOUVELLE_CONSTANTE', true);

if (isset($GLOBALS['meta']['adresse_site'])) {
	$adresse = $GLOBALS['meta']['adresse_site'];
}
```

### Keywords, control structures, operators

- PHP reserved keywords and types are lowercase.
- Prefer short type names (`bool`, `int`, etc.).
- Opening brace for control structures is on the same line.
- Put spaces around binary operators.
- Unary operators stick to their operand.
- Use short array syntax `[]`.

Example:

```php
$hypotenuse = sqrt(($a * $a) + ($b * $b));

$tableau = [
	'cle' => 'valeur',
];
```

### Strings

- Use single quotes by default.
- Use double quotes only when interpolation or escape sequences are needed.

Example:

```php
$chaine = "<a href=\"$url\">Lien</a>\n";
$chaine2 = 'Une simple phrase : ' . $autre_chose;
```

## What your ECS config enforces directly

From the provided analysis function:

- Tab indentation is enforced (`withSpacing(Option::INDENTATION_TAB)`).
- PSR-12 baseline is enabled (`SetList::PSR_12`) with additional cleanup/simplify sets.
- Function opening braces on same line are enforced via `CurlyBracesPositionFixer`.
- Long array syntax is forbidden (`DisallowLongArraySyntaxSniff`), so short arrays `[]` are enforced.
- Some strict operator spacing and unary/not-operator fixers are explicitly skipped.
- Assignment in conditions is explicitly allowed (sniff skipped).

Implication: this config enforces an important subset of SCS1, but not every semantic convention.
For example, snake_case naming or default single-quote preference are mostly convention-level unless
additional dedicated rules are added.

## Tooling

- SCS1 rule package ecosystem: `spip/coding-standards` (PHP_CodeSniffer).
- ECS-based projects can enforce a mixed rule set (PHP-CS-Fixer + selected PHPCS sniffs),
  as in the provided configuration.
- In this workspace for SPIP core checks: run `vendor/bin/phpcs` from `spip/`.

## Configuring ECS and Rector

### Setup dependencies

Add these dev dependencies to `composer.json`:

```json
{
	"name": "mon-organisation/plugin/mon-filtre",
    "require-dev": {
        "spip-league/easy-coding-standard": "^1.0",
        "rector/rector": "^2.0",
        "spip-league/rector": "dev-main"
    },
    "repositories": {
        "spip": {
            "type": "composer",
            "url": "https://get.spip.net/composer"
        }
    },
    "scripts": {
        "check-cs": "vendor/bin/ecs check --ansi",
        "fix-cs": "vendor/bin/ecs check --fix --ansi",
        "rector": "vendor/bin/rector process --ansi",
        "rector-dry-run": "vendor/bin/rector process --dry-run --ansi"
    }
}
```

Then run `composer install` to install the tools.

### Configuring ECS

Create `ecs.php` at the plugin root:

```php
<?php

use SpipLeague\EasyCodingStandard\Set\SetList;
use Symplify\EasyCodingStandard\Config\ECSConfig;

return ECSConfig::configure()
	->withSets([SetList::SPIP])
	->withPaths([__DIR__])
	->withRootFiles()
	->withSkip([__DIR__ . '/lib', __DIR__ . '/vendor'])
;
```

Key options:
- `withSets([SetList::SPIP])` — Applies the SPIP-specific ruleset (tab indentation, brace positioning, etc.)
- `withPaths([__DIR__])` — Scans the plugin root directory
- `withRootFiles()` — Includes root-level PHP files like `paquet.xml` (if `.php` extension is added by SPIP conventions)
- `withSkip()` — Excludes `lib/` and `vendor/` directories from analysis

**Usage:**
- `composer check-cs` — Report style violations
- `composer fix-cs` — Auto-fix style violations

### Configuring Rector

Create `rector.php` at the plugin root:

```php
<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Set\ValueObject\LevelSetList;
use SpipLeague\Component\Rector\Set\SpipSetList;

return RectorConfig::configure()
	->withPaths([__DIR__])
	->withRootFiles()
	->withSets([SpipSetList::SPIP_41, LevelSetList::UP_TO_PHP_74])
	->withPreparedSets(
		deadCode: true,
		codeQuality: true,
		#codingStyle: true,
		#typeDeclarations: true,
		privatization: true,
		naming: true,
		instanceOf: true,
		earlyReturn: true,
		strictBooleans: true,
	)
	->withSkip([__DIR__ . '/lib', __DIR__ . '/vendor'])
;
```

Key options:
- `withSets([SpipSetList::SPIP_41, LevelSetList::UP_TO_PHP_74])` — Apply SPIP 4.1 refactorings and PHP 7.4 upgrade rules
- `withPreparedSets()` — Enable refactoring categories:
  - `deadCode` — Remove unreachable code
  - `codeQuality` — Improve code patterns
  - `privatization` — Convert `protected` to `private` where safe
  - `naming` — Improve variable/method names
  - `instanceOf` — Modernize type checks
  - `earlyReturn` — Simplify control flow
  - `strictBooleans` — Enforce strict boolean comparisons
  - Commented-out options can be enabled as needed
- `withSkip()` — Excludes directories from refactoring

**Usage:**
- `composer rector-dry-run` — Preview refactorings without modifying files
- `composer rector` — Apply refactorings

### Typical workflow

1. Run `composer check-cs` to identify style issues
2. Run `composer fix-cs` to auto-fix style issues
3. Run `composer rector-dry-run` to preview refactorings
4. Review changes and run `composer rector` when ready
5. Commit fixed code with clear messages (e.g., "style: apply SPIP coding standards")


If there is any ambiguity, prefer project-local conventions already present in the target codebase.
