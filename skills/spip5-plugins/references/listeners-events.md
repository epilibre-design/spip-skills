# Pipeline listeners and events

---

## Listener

```php
<?php

declare(strict_types=1);

namespace Acme\Pipeline\Listener;

use Spip\Framework\Pipeline\Event\AffichageFinalEvent;
use SpipLeague\Bridge\Pipeline\Attribute\AsPipelineListener;

final class AffichageFinalListener
{
	#[AsPipelineListener]                       // pipeline deduced from the event type
	public function onAffichageFinal(AffichageFinalEvent $event): void
	{
		if (!$event->isHtmlPage()) {            // RSS, JSON, AJAX fragments
			return;
		}
		$event->appendToBody('<!-- acme -->');  // before </body>
	}
}
```

The method returns `void`: it changes the event, it does not return the flux.

`affichage_final` sees every page, not only HTML. Without `</body>`, `appendToBody()` **appends at the end** of the
output (verified: the comment lands after `</rss>` on `spip.php?page=backend`); `appendToHead()` does nothing
without `</head>`. Test `isHtmlPage()` (true when the output contains `<html` or `<!doctype html`) before adding
markup.

`#[AsPipelineListener(?string $pipeline = null, string $position = 'normal', string $method = '__invoke')]`:

- typed event → leave `$pipeline` empty;
- pipeline without a typed event → type `SpipLeague\Bridge\Pipeline\PipelineEvent` **and** name it:
  `#[AsPipelineListener('configurer_liste_metas')]`; `getSubject()` / `setSubject()` = old `$flux['data']` (or the
  whole flux), `getArgument('k')`, `getArguments()` = old `$flux['args']`;
- union type `AfficheGaucheEvent|AfficheDroiteEvent` → one registration per pipeline;
- `position`: `prepend`, `normal`, `append`;
- on a class the attribute targets `__invoke` (or `method:`); prefer named `onXxx()` methods;
- no pipeline deducible → explicit exception at compile time.

## `pre_edition` (verified)

```php
use Spip\Framework\Pipeline\Event\PreEditionEvent;

#[AsPipelineListener]
public function onPreEdition(PreEditionEvent $event): void
{
	if ($event->getSqlTable() !== 'spip_articles' || $event->getAction() !== 'modifier') {
		return;
	}
	$champs = $event->getSubject();             // only the fields being saved
	if (isset($champs['titre'])) {
		$champs['titre'] = mb_strtoupper($champs['titre']);
		$event->setSubject($champs);
	}
}
```

`PreEditionEvent` has **no** `getTable()` / `setField()` (those are on `PreInsertionEvent`); the upstream
documentation example using them is wrong.

## Events of the core (5.0.0-beta)

Exact list on the instance, plugin events included: `bin/spip spip:pipeline:events`. Namespace
`Spip\Framework\Pipeline\Event\` unless stated.

| Pipeline | Event | Useful methods |
|---|---|---|
| `affichage_final` (+ `_prive`) | `AffichageFinalEvent` (`AffichageFinalPriveEvent`) | `getHtml()`, `setHtml()`, `appendToBody()`, `appendToHead()`, `prependToHead()`, `replace()`, `replacePattern()`, `isHtmlPage()` |
| `affichage_entetes_final` (+ `_prive`) | `AffichageEntetesFinalEvent` (`…PriveEvent`) | |
| `insert_head` | `InsertHeadEvent` | `addScript($src, $type = 'text/javascript', $attributes = [])`, `addModuleScript()`, `addInlineScript()`, `addMeta()`, `addMetaProperty()`, `addLink()`, `addPreload()`, `addPrefetch()`, `append()` |
| `insert_head_css` | `InsertHeadCssEvent` | `addStylesheet()`, `addMediaStylesheet()`, `addPrintStylesheet()`, `addInlineStyle()`, `append()` |
| `header_prive` / `header_prive_css` | `HeaderPriveEvent` / `HeaderPriveCssEvent` | same as public |
| `formulaire_charger` | `FormulaireChargerEvent` | `getFormName()`, `getFormArgs()`, `getValue()`, `setValue()`, `getValues()`, `setValues()`, `mergeValues()`, `removeValue()` |
| `formulaire_verifier` | `FormulaireVerifierEvent` | `addError()`, `addGlobalError()`, `hasErrors()`, `hasError()`, `getError()`, `getErrors()`, `setErrors()`, `removeError()`, `clearErrors()` |
| `formulaire_traiter` | `FormulaireTraiterEvent` | `setSuccessMessage()`, `setRedirect()` |
| `formulaire_receptionner`, `formulaire_verifier_etape` | `FormulaireReceptionnerEvent`, `FormulaireVerifierEtapeEvent` | |
| `formulaire_fond`, `editer_contenu_objet`, `formulaire_admin`, `affiche_formulaire_login` | `FormulaireHtmlEvent`, `FormulaireHtmlEditerObjetEvent`, `FormulaireHtmlAdministrationEvent`, `FormulaireHtmlLoginEvent` | `getHtml()`, `getFormName()`, `getContext()` |
| `traduire` | `Spip\Prive\Pipeline\Event\FormulaireHtmlTraduireEvent` | |
| `pre_insertion` | `PreInsertionEvent` | `getTable()`, `getField()`, `setField()`, `hasField()`, `mergeFields()` |
| `post_insertion` | `PostInsertionEvent` | `getTable()`, `getInsertedId()`, `getObjetType()`, `getField()` |
| `pre_edition` / `post_edition` | `PreEditionEvent` / `PostEditionEvent` | `getSqlTable()`, `getTableObjet()`, `getObjet()`, `getIdObjet()`, `getAction()`, `getChampsAnciens()`, fields via `getSubject()`/`setSubject()` |
| `pre_edition_lien` / `post_edition_lien` | `PreEditionLienEvent` / `PostEditionLienEvent` | |
| `pre_propre`, `post_propre`, `pre_typo`, `post_typo`, `pre_liens` | `PrePropreEvent`, `PostPropreEvent`, `PreTypoEvent`, `PostTypoEvent`, `PreLiensEvent` | |
| `styliser` | `StyliserEvent` | `getFond()`, `isFond()`, `getContexte()`, `hasSquelette()`, `setSquelette()`, `getExtension()`, `setExtension()` |
| `recuperer_fond` | `RecupererFondEvent` | |
| `affiche_gauche` / `affiche_milieu` / `affiche_droite` | `AfficheGaucheEvent` / `AfficheMilieuEvent` / `AfficheDroiteEvent` | `getExec()`, `addBox()`, `appendHtml()` |
| `boite_infos` | `BoiteInfosEvent` | |
| `declarer_tables_objets_sql` | `DeclarerTablesObjetsSqlEvent` | `declareTable()`, `addField()`, `addEditableField()`, `modifyTable()`, `addJoinForAllObjects()`, `getTable()` |
| `declarer_tables_principales` / `_auxiliaires` / `_interfaces` | `DeclarerTablesPrincipalesEvent` / `…AuxiliairesEvent` / `…InterfacesEvent` | `getTables()`/`setTables()`, `getInterfaces()`/`setInterfaces()` |
| `taches_generales_cron` | `TachesGeneralesCronEvent` | `addTask($name, $periode)`, `removeTask()`, `hasTask()`, `getTasks()`, `setTasks()` |
| `trig_logout`, `trig_supprimer_objets_lies` | `TrigLogoutEvent`, `TrigSupprimerObjetsLiesEvent` | |
| `optimiser_base_disparus`, `afficher_nombre_objets_associes_a`, `modeles_publics_autorises` | `OptimiserBaseDisparusEvent`, `AfficherNombreObjetsAssociesAEvent`, `ModelesPublicsAutorisesEvent` | |

Methods of an event class not listed here: read the class in `vendor/spip/framework/src/Pipeline/Event/`.

## Own pipeline

Declare an event: a class extending `PipelineEvent` with `#[AsPipelineEvent('acme_preparer')]`
(`SpipLeague\Bridge\Pipeline\Attribute\AsPipelineEvent`). Call it:

```php
$event = new AcmePreparerEvent($data);
pipeline($event);
$data = $event->getSubject();
// legacy form, still accepted: $data = pipeline('acme_preparer', $data, ['id_objet' => 12]);
```
