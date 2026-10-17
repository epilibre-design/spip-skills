# Private space: pages, menus, tabs, assets

---

## Page + menu entry (verified)

`prive/squelettes/contenu/acme_outils.html` — the page `ecrire/?exec=acme_outils`:

```html
<h1 class="grostitre"><:acme:titre_outils:></h1>
…
```

Optional: `prive/squelettes/navigation/acme_outils.html` (left column), `prive/squelettes/extra/acme_outils.html`.

`config/menus.php` of the plugin — returns a static array:

```php
<?php

declare(strict_types=1);

return [
	'acme_outils' => [
		'titre' => 'acme:titre_outils',
		'parent' => 'menu_edition',
		'icone' => 'images/acme-16.png',
	],
];
```

Keys: `titre` (language key), `parent`, `icone`, optional `action` (exec, defaults to the entry key), `parametres`,
`position`. Collected at warmup into `tmp/cache/boot/menus.php`; verified: the entry appears under `menu_edition`
for an administrator.

Parents: `menu_accueil`, `menu_edition`, `menu_publication`, `menu_activite`, `menu_apparence` (ex-`menu_squelette`),
`menu_administration`, `menu_configuration`. No `<menu>` in `paquet.xml`, no `bando_*` parents. Upstream flags this
format as "may still change".

Tabs: `config/onglets.php`, same shape, `parent` = the tab bar name.

Restrict access: `autoriser_acmeoutils_menu_dist()` (menu) and a `#AUTORISER` test in the squelette (page); the
`autoriser` model is unchanged.

## Language file

`lang/acme_fr.php`:

```php
<?php

return [
	'titre_outils' => 'Outils Acme',
];
```

## CSS and JS

Listeners instead of `<script>` / `<style>` in `paquet.xml`:

```php
#[AsPipelineListener]
public function onHeaderPrive(HeaderPriveEvent $event): void
{
	$event->addScript(timestamp(find_in_path('javascript/acme.js')));
}

#[AsPipelineListener]
public function onInsertHeadCss(InsertHeadCssEvent $event): void
{
	$event->addStylesheet(timestamp(find_in_path('css/acme.css')));
}
```

Public: `insert_head` / `insert_head_css`; private: `header_prive` / `header_prive_css`. Output of `insert_head` is
stored in the page cache: check with `?var_mode=recalcul`.

JavaScript: ESM modules `config.js` (replaces `jQuery.spip`) and `ajaxCallback.js` (`ajaxReload`, `onAjaxLoad`,
`animateLoading`, `parametre_url`, `followLink`, …); non-ESM code keeps `spipConfig` through `retrocompat.js`.
`?var_mode=debug_js` traces deprecated JS usage.
