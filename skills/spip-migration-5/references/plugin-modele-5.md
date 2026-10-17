# Plugin model in SPIP 5.0

Real example to imitate: `plugins-dist/spip/bigup/` (5.0): `config/services.php`, `src/Pipeline/Listener/*`,
`src/Cron/Task/*`, no `<pipeline>` in `paquet.xml`.

---

## Files of a migrated plugin

```
acme/
├── paquet.xml              compatibilite="[5.0.0;5.*]"  (or [4.4.0;5.*], see double-compat.md)
├── composer.json           autoload psr-4 (+ require spip/framework when installed through Composer)
├── config/
│   ├── services.php        registers src/ as services (autowire + autoconfigure)
│   ├── menus.php           optional — private-space menus
│   └── onglets.php         optional — tabs
└── src/
    ├── Pipeline/Listener/  #[AsPipelineListener] classes
    └── Cron/Task/          #[AsCronTask] classes
```

### `composer.json`

```json
{
  "name": "acme/acme",
  "type": "spip-plugin",
  "require": { "spip/framework": "^5.0" },
  "autoload": { "psr-4": { "Acme\\": "src/" } }
}
```

Plugin activated through **SVP** (not in the root `composer.json`): only its PSR-4 autoload is registered; its
`require` are **not** installed — embed a `vendor/` or install the plugin through the root `composer.json`.

### `config/services.php`

```php
<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $container): void {
    $services = $container->services()
        ->defaults()
        ->autowire()
        ->autoconfigure();          // discovers #[AsPipelineListener], #[AsCronTask], #[AsGuard]

    $services->load('Acme\\', '../src/');
};
```

`config/services.php` is collected automatically for every **active** plugin (`PluginSymfonyServicesCollector`): nothing to
declare in `paquet.xml`. Without `autoconfigure()` and `load()` of the class, attributes are **never** read.
Plugin parameters: `$container->parameters()->set('acme.xxx', …)` (prefix them with the plugin name).

## Pipeline function → listener

```php
// 4.x: <pipeline nom="affichage_final" inclure="acme_pipelines.php" /> + function acme_affichage_final($html)

namespace Acme\Pipeline\Listener;

use Spip\Framework\Pipeline\Event\AffichageFinalEvent;
use SpipLeague\Bridge\Pipeline\Attribute\AsPipelineListener;

final class AffichageFinalListener
{
    #[AsPipelineListener]                       // no name: deduced from the event type
    public function onAffichageFinal(AffichageFinalEvent $event): void
    {
        $event->appendToBody('<script src="' . timestamp(find_in_path('js/acme.js')) . '"></script>');
    }
}
```

Adding a `<script>` to every page: prefer `insert_head` + `addScript()` (head of public pages) over `affichage_final`,
which rewrites every served HTML page; keep `affichage_final` when the original behaviour (end of `<body>`) matters.

Then **remove** the `<pipeline>` line and the function (keep a thin function only if third-party code calls it directly).
Legacy functions and listeners can coexist during the transition.

### Rules of `#[AsPipelineListener(?string $pipeline = null, string $position = 'normal', string $method = '__invoke')]`

- Typed event argument → leave `$pipeline` empty (deduced from `#[AsPipelineEvent('…')]` on the event class).
- Union type `AfficheGaucheEvent|AfficheDroiteEvent` → one registration per pipeline.
- No dedicated event → `SpipLeague\Bridge\Pipeline\PipelineEvent` **and** the explicit name: `#[AsPipelineListener('configurer_liste_metas')]`.
  `getSubject()/setSubject()` = old `$flux['data']` (or `$flux`); `getArgument('k')`, `getArguments()` = old `$flux['args']`.
- `position`: `prepend`, `normal`, `append`.
- On a class, the attribute targets `__invoke` (or `method:`). Prefer named methods `onXxx()`.
- Compilation fails with an explicit exception when no pipeline can be deduced.

### Core events (`vendor/spip/framework/src/Pipeline/Event/`) — exact list: `bin/spip spip:pipeline:events`

| Pipeline | Event | Useful methods |
|---|---|---|
| `affichage_final` (+ `_prive`) | `AffichageFinalEvent` (`AffichageFinalPriveEvent`) | `getHtml()`, `setHtml()`, `appendToBody()` (inserts before `</body>`, else appends), `appendToHead()`, `prependToHead()`, `replace()`, `replacePattern()`, `isHtmlPage()` |
| `affichage_entetes_final` (+ `_prive`) | `AffichageEntetesFinalEvent` (`…PriveEvent`) | |
| `insert_head` | `InsertHeadEvent` | `addScript(string $src, string $type = 'text/javascript', array $attributes = [])`, `addModuleScript()`, `addInlineScript()`, `addMeta()`, `addMetaProperty()`, `addLink()`, `addPreload()`, `addPrefetch()`, `append()` |
| `insert_head_css` | `InsertHeadCssEvent` | `addStylesheet()`, `addMediaStylesheet()`, `addPrintStylesheet()`, `addInlineStyle()`, `append()` |
| `header_prive` / `header_prive_css` | `HeaderPriveEvent` / `HeaderPriveCssEvent` | same as public |
| `formulaire_charger` | `FormulaireChargerEvent` | `getFormName()`, `getFormArgs()`, `getValue()`, `setValue()`, `mergeValues()`, `removeValue()` |
| `formulaire_verifier` | `FormulaireVerifierEvent` | `addError()`, `addGlobalError()`, `hasErrors()`, `getErrors()`, `removeError()` |
| `formulaire_traiter` | `FormulaireTraiterEvent` | `setSuccessMessage()`, `setRedirect()` |
| `formulaire_receptionner`, `formulaire_verifier_etape` | `FormulaireReceptionnerEvent`, `FormulaireVerifierEtapeEvent` | |
| `formulaire_fond`, `editer_contenu_objet`, `formulaire_admin`, `affiche_formulaire_login`, `traduire` | `FormulaireHtmlEvent`, `FormulaireHtmlEditerObjetEvent`, `FormulaireHtmlAdministrationEvent`, `FormulaireHtmlLoginEvent`, `FormulaireHtmlTraduireEvent` | `getHtml()`, `getFormName()`, `getContext()` |
| `pre_insertion` | `PreInsertionEvent` | `getTable()`, `getField()`, `setField()`, `hasField()`, `mergeFields()` |
| `post_insertion` | `PostInsertionEvent` | `getTable()`, `getInsertedId()`, `getObjetType()`, `getField()` |
| `pre_edition` / `post_edition` | `PreEditionEvent` / `PostEditionEvent` | `getSqlTable()`, `getTableObjet()`, `getObjet()`, `getIdObjet()`, `getAction()`, `getChampsAnciens()`; fields via `getSubject()`/`setSubject()` — **no `getTable()`/`setField()`** |
| `pre_edition_lien` / `post_edition_lien` | `PreEditionLienEvent` / `PostEditionLienEvent` | |
| `pre_propre`, `post_propre`, `pre_typo`, `post_typo`, `pre_liens` | `PrePropreEvent`, `PostPropreEvent`, `PreTypoEvent`, `PostTypoEvent`, `PreLiensEvent` | |
| `styliser` | `StyliserEvent` | `getFond()`, `isFond()`, `getContexte()`, `hasSquelette()`, `setSquelette()`, `getExtension()` |
| `recuperer_fond` | `RecupererFondEvent` | |
| `affiche_gauche` / `affiche_milieu` / `affiche_droite` | `AfficheGaucheEvent` / `AfficheMilieuEvent` / `AfficheDroiteEvent` | `getExec()`, `addBox()`, `appendHtml()` |
| `boite_infos` | `BoiteInfosEvent` | |
| `declarer_tables_objets_sql` | `DeclarerTablesObjetsSqlEvent` | `declareTable()`, `addField()`, `addEditableField()`, `modifyTable()`, `addJoinForAllObjects()`, `getTable()` |
| `declarer_tables_principales` / `_auxiliaires` / `_interfaces` | `DeclarerTablesPrincipalesEvent` / `…AuxiliairesEvent` / `…InterfacesEvent` | `getTables()`/`setTables()`, `getInterfaces()`/`setInterfaces()` |
| `taches_generales_cron` | `TachesGeneralesCronEvent` | prefer `#[AsCronTask]` |
| `trig_logout`, `trig_supprimer_objets_lies` | `TrigLogoutEvent`, `TrigSupprimerObjetsLiesEvent` | |
| `optimiser_base_disparus`, `afficher_nombre_objets_associes_a`, `modeles_publics_autorises` | matching `…Event` | |

`pre_edition` example:

```php
#[AsPipelineListener]
public function onPreEdition(PreEditionEvent $event): void
{
    if ($event->getSqlTable() !== 'spip_articles') {
        return;
    }
    $champs = $event->getSubject();
    $champs['maj'] = date('Y-m-d H:i:s');
    $event->setSubject($champs);
}
```

### Calling a pipeline from plugin code

```php
$html = pipeline('affichage_final', $html);                         // legacy, still accepted
$champs = pipeline('acme_pipeline', $champs, ['id_objet' => 12]);   // 3-argument form (args) preferred
$event = new AffichageFinalEvent($html); pipeline($event); $html = $event->getHtml(); // typed
```

A plugin may declare its own event (`#[AsPipelineEvent('acme_preparer')]` on a class extending `PipelineEvent`).

## Cron task

```php
// 4.x: acme_taches_generales_cron() adds 'acme_purge' => 3600 ; genie/acme_purge.php returns 1

namespace Acme\Cron\Task;

use SpipLeague\Bridge\Pipeline\Attribute\AsCronTask;

#[AsCronTask(name: 'acme_purge', periode: 3600)]
final class PurgeTask
{
    public function __invoke(): int
    {
        include_spip('inc/acme');   // procedural code is not autoloaded: load it, or move it into the class
        acme_purger();
        return 0;          // 0 = success. > 0 = FAILURE → re-planned immediately with lower priority
    }
}
```

- Signature: `AsCronTask(string $name, int $periode = 0, int $priorite = 0, string $method = '__invoke')`, repeatable,
  on a class or a method.
- `periode: 0` → registered but **never scheduled**; avoid < 30 s.
- Container parameters: `spip.cron.<name>.enabled` (`false` disables), `spip.cron.<name>.period` (overrides).
- Remove the `taches_generales_cron` entry and the `genie/` file (the pipeline still exists for legacy code).
- Legacy `genie_*` functions keep their convention (`>= 0` success, `< 0` failure); only services use the Unix convention.

## Logs, config, inputs inside a service

Inject instead of calling the façade:

```php
public function __construct(
    #[Autowire(service: 'spip.acme.logger')] private readonly LoggerInterface $logger,
    private readonly ConfigRepositoryInterface $config,
    private readonly RequestStack $requestStack,
) {}
```

Dedicated logger channel in `services.php` (`use function Symfony\…\Configurator\service;`):

```php
$services->set('spip.acme.logger', LoggerInterface::class)
    ->factory([service('spip.logger_registry'), 'get'])
    ->args(['acme']);
```

Procedural code: `Spip::logger('acme')`, `Spip::config('acme/x')`, `Spip::input('id')`.

Site-wide settings go in `config/custom.php` (never in `config/spip/*.php`):

```php
return static function (ContainerConfigurator $container): void {
    $container->parameters()->set('spip.logger.max_level', 'debug');
};
```

## Private-space menus

`config/menus.php` / `config/onglets.php` return static arrays (keys `titre`, `parent`, `icone`, `action`, `parametres`, `position`):

```php
return [
    'acme_outil' => ['titre' => 'acme:titre_outil', 'parent' => 'menu_edition', 'icone' => 'images/acme-16.png', 'action' => 'acme_outil'],
];
```

Standard parents: `menu_accueil`, `menu_edition`, `menu_publication`, `menu_activite`, `menu_apparence` (ex-`menu_squelette`),
`menu_administration`, `menu_configuration`. `bando_*` → `menu_*`. This part is flagged "may still change" upstream.

## Assets

`<script>` / `<style>` in `paquet.xml` are deprecated → listener on `insert_head` / `insert_head_css` (public) or
`header_prive` / `header_prive_css` (private), with `addScript()` / `addStylesheet()`.

JavaScript: ESM modules `config.js` (replaces `jQuery.spip`) and `ajaxCallback.js` (`ajaxReload`, `onAjaxLoad`,
`animateLoading`, `parametre_url`, `followLink`, …). Non-ESM code keeps `spipConfig` through `retrocompat.js`.
`?var_mode=debug_js` traces deprecated JS usage.

## Security rules (Guard)

`config/ecran_securite.php` is no longer included automatically; `ECRAN_SECURITE_LOAD` → `spip.guard.load_threshold`.
Custom rule: class implementing `SpipLeague\Component\Guard\GuardRuleInterface` with `#[AsGuard(priority: 50)]`, returning a
`GuardVerdict` from `evaluate(GuardContext $context)`; read the request from the context, never `$_GET`/`$_POST`.

## `options.php` limits

`options.php` still runs, but **after** the container is compiled: redefining `_DIR_*`, writing `$GLOBALS['spip_pipeline']`
or other "frozen" declarations has no effect. Use `config/custom.php` (site) or `config/services.php` (plugin).
