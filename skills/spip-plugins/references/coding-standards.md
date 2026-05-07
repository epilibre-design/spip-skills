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

## Source

- Canonical article: Standard "SCS1" (SPIP website, fr_article6677).
- Snapshot basis observed: article updated 2025-03-03.

If there is any ambiguity, prefer project-local conventions already present in the target codebase.
