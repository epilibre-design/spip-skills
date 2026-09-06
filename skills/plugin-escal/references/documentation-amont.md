# Escal — carte de la documentation amont

> **Statut de cette page : secondaire.** <https://escal.edu.ac-lyon.fr/spip/> is the author's
> documentation site, written **for human beginners** — it explains *what to click*, not what the
> code does, and it can lag behind the release.
>
> **The plugin source is the source of truth.** On any discrepancy — a key name, a filename, a
> default value, a list of options — read the code and follow the code. Use the links below to
> point a *human* at a tutorial, or to recover intent when the code is ambiguous. Never quote a
> figure or an identifier from these pages without confirming it in the source.

Where to confirm what, in the source:

| Question | Fichier qui fait foi |
|---|---|
| Which noisettes are allowed in a slot | `formulaires/configurer_escal_choix_blocs.php`, the `'data'` arrays |
| What a key defaults to | the `'defaut'` of its saisie, in `formulaires/configurer_escal_*.php` |
| What a noisette renders | `inclusions/inc-‹nom›.html` |
| Which mots-clés exist | `shema_escal()` in `escal_fonctions.php` |
| How a page is routed | the first boucles of the root `*.html` squelette |
| Which stylesheet wins | the order of `<link>` in `inclusions/inc-head.html` |

---

## Installer et démarrer

| Sujet | Article |
|---|---|
| Présentation d'Escal | [article115](https://escal.edu.ac-lyon.fr/spip/spip.php?article115) |
| Installer Escal en plugin | [article114](https://escal.edu.ac-lyon.fr/spip/spip.php?article114) |
| Escal est international (langues) | [article151](https://escal.edu.ac-lyon.fr/spip/spip.php?article151) |
| Mes premiers pas avec Escal | [article173](https://escal.edu.ac-lyon.fr/spip/spip.php?article173) |
| `/squelettes`, `perso.css` et les autres | [article302](https://escal.edu.ac-lyon.fr/spip/spip.php?article302) |

## Paramétrer

| Sujet | Article |
|---|---|
| Mise en page générale (layouts) | [article1](https://escal.edu.ac-lyon.fr/spip/spip.php?article1) |
| Jouer avec le bandeau | [article193](https://escal.edu.ac-lyon.fr/spip/spip.php?article193) |
| Jouer avec les couleurs | [article194](https://escal.edu.ac-lyon.fr/spip/spip.php?article194) |
| Les cadres de couleur | [article15](https://escal.edu.ac-lyon.fr/spip/spip.php?article15) |
| Les noisettes (vue d'ensemble) | [article31](https://escal.edu.ac-lyon.fr/spip/spip.php?article31) |
| Les mots-clés | [article59](https://escal.edu.ac-lyon.fr/spip/spip.php?article59) |
| Agenda et évènements | [article221](https://escal.edu.ac-lyon.fr/spip/spip.php?article221) |
| Les fichiers de langues | [article152](https://escal.edu.ac-lyon.fr/spip/spip.php?article152) |
| Des modèles | [article251](https://escal.edu.ac-lyon.fr/spip/spip.php?article251) |

## Noisettes — tête et pied

| Noisette | Article |
|---|---|
| Le head (`inc-head`) | [article22](https://escal.edu.ac-lyon.fr/spip/spip.php?article22) |
| Le bandeau (`inc-bandeau`) | [article25](https://escal.edu.ac-lyon.fr/spip/spip.php?article25) |
| Le menu horizontal (`inc-menu*`) | [article29](https://escal.edu.ac-lyon.fr/spip/spip.php?article29) |
| Le pied (`inc-pied`) | [article30](https://escal.edu.ac-lyon.fr/spip/spip.php?article30) |
| Boîte modale (`modale.html`, mot `popup`) | [article448](https://escal.edu.ac-lyon.fr/spip/spip.php?article448) |
| Identification simplifiée (`inc-identification_light`) | [article83](https://escal.edu.ac-lyon.fr/spip/spip.php?article83) |

## Noisettes centrales

| Noisette | Valeur | Article |
|---|---|---|
| Annonce | `blocune*` = `annonce` | [article174](https://escal.edu.ac-lyon.fr/spip/spip.php?article174) |
| Annonces défilantes | `blocune*` = `annonce_defilant` | [article179](https://escal.edu.ac-lyon.fr/spip/spip.php?article179) |
| À la une | `blocune*` = `a_la_une` | [article5](https://escal.edu.ac-lyon.fr/spip/spip.php?article5) |
| Derniers articles (onglet) | `onglet*` = `derniersarticles` | [article17](https://escal.edu.ac-lyon.fr/spip/spip.php?article17) |
| Plan du site (onglet) | `onglet*` = `plansite` | [article18](https://escal.edu.ac-lyon.fr/spip/spip.php?article18) |
| Articles sélectionnés par mot-clé | `onglet*` = `articlesmotcle` | [article281](https://escal.edu.ac-lyon.fr/spip/spip.php?article281) |
| Article d'accueil | `onglet*` = `articleaccueil` | [article182](https://escal.edu.ac-lyon.fr/spip/spip.php?article182) |
| Article archive | `onglet*` = `articlearchive` | [article185](https://escal.edu.ac-lyon.fr/spip/spip.php?article185) |
| Rubrique accueil (1 à 5) | `onglet*` = `rubrique`…`rubrique5` | [article186](https://escal.edu.ac-lyon.fr/spip/spip.php?article186) |
| Mon article (1 à 5) | `onglet*` = `mon_article`…`mon_article5` | [article188](https://escal.edu.ac-lyon.fr/spip/spip.php?article188) |
| Sur le web (onglet) | `onglet*` = `sites_accueil` | [article311](https://escal.edu.ac-lyon.fr/spip/spip.php?article311) |
| Les rubriques (`inc-rubrique_normal`) | routage | [article19](https://escal.edu.ac-lyon.fr/spip/spip.php?article19) |
| Les articles (`inc-article`) | routage | [article20](https://escal.edu.ac-lyon.fr/spip/spip.php?article20) |
| Le forum des articles | routage | [article21](https://escal.edu.ac-lyon.fr/spip/spip.php?article21) |
| Trombinoscope (`inc-rubrique_trombino`) | mot `type_rubrique`=`trombino` | [article58](https://escal.edu.ac-lyon.fr/spip/spip.php?article58) |
| Les événements | plugin Agenda | [article208](https://escal.edu.ac-lyon.fr/spip/spip.php?article208) |
| Le forum du site | mot `forum` sur un secteur | [article171](https://escal.edu.ac-lyon.fr/spip/spip.php?article171) |

## Noisettes latérales

| Noisette | Valeur de slot | Article |
|---|---|---|
| Édito | `edito` | [article23](https://escal.edu.ac-lyon.fr/spip/spip.php?article23) |
| Accès direct | `acces_direct` | [article24](https://escal.edu.ac-lyon.fr/spip/spip.php?article24) |
| Derniers articles | `derniers_articles` | [article26](https://escal.edu.ac-lyon.fr/spip/spip.php?article26) |
| Identification | `identification` | [article27](https://escal.edu.ac-lyon.fr/spip/spip.php?article27) |
| Dans la même rubrique | `meme_rub` | [article28](https://escal.edu.ac-lyon.fr/spip/spip.php?article28) |
| Articles les plus vus | `top` | [article78](https://escal.edu.ac-lyon.fr/spip/spip.php?article78) |
| Mini calendrier | `calendrier` | [article81](https://escal.edu.ac-lyon.fr/spip/spip.php?article81) |
| Événements à venir | `evenements` | [article77](https://escal.edu.ac-lyon.fr/spip/spip.php?article77) |
| Sur le web | `sites` | [article82](https://escal.edu.ac-lyon.fr/spip/spip.php?article82) |
| Actus | `actus` | [article130](https://escal.edu.ac-lyon.fr/spip/spip.php?article130) |
| Mots-clés associés | `nav_mots` | [article131](https://escal.edu.ac-lyon.fr/spip/spip.php?article131) |
| Menu de mots-clés | `nav_mots2` | [article133](https://escal.edu.ac-lyon.fr/spip/spip.php?article133) |
| Navigation par mots-clés | `nav_mots2` | [article312](https://escal.edu.ac-lyon.fr/spip/spip.php?article312) |
| Photos au hasard | `photos` | [article144](https://escal.edu.ac-lyon.fr/spip/spip.php?article144) |
| Vidéos | `video_accueil` | [article197](https://escal.edu.ac-lyon.fr/spip/spip.php?article197) |
| Articles de rubrique | `articles_de_rubrique` | [article279](https://escal.edu.ac-lyon.fr/spip/spip.php?article279) |
| Sites favoris | `sites_favoris` | [article181](https://escal.edu.ac-lyon.fr/spip/spip.php?article181) |
| Derniers articles syndiqués | `sites_recents` | [article291](https://escal.edu.ac-lyon.fr/spip/spip.php?article291) |
| Derniers commentaires | `derniers_comments` | [article178](https://escal.edu.ac-lyon.fr/spip/spip.php?article178) |
| Statistiques | `stats` | [article126](https://escal.edu.ac-lyon.fr/spip/spip.php?article126) |
| Annuaire auteurs | `liste_auteurs` | [article201](https://escal.edu.ac-lyon.fr/spip/spip.php?article201) |
| Article libre 1 à 5 | `article_libre1`…`5` | [article200](https://escal.edu.ac-lyon.fr/spip/spip.php?article200) |
| Noisette à personnaliser | `perso` | [article190](https://escal.edu.ac-lyon.fr/spip/spip.php?article190) |
| Rainette | `rainette` | [article192](https://escal.edu.ac-lyon.fr/spip/spip.php?article192) |
| À découvrir | `decouvrir_articles` | [article180](https://escal.edu.ac-lyon.fr/spip/spip.php?article180) |
| À télécharger | `documents_article` / `documents_rubrique` | [article191](https://escal.edu.ac-lyon.fr/spip/spip.php?article191) |
| Recherche multi-critères | `recherche_multi` | [article198](https://escal.edu.ac-lyon.fr/spip/spip.php?article198) |
| Menu vertical dépliant | `inc-menu_vertical` | [article102](https://escal.edu.ac-lyon.fr/spip/spip.php?article102) |
| Menu vertical déroulant à droite | `inc-menu_vertical_2` | [article112](https://escal.edu.ac-lyon.fr/spip/spip.php?article112) |

## Pages spéciales

| Page | Article |
|---|---|
| Agenda et Mini-calendrier | [article34](https://escal.edu.ac-lyon.fr/spip/spip.php?article34) |
| Annuaire de sites | [article113](https://escal.edu.ac-lyon.fr/spip/spip.php?article113) |
| Auteur | [article35](https://escal.edu.ac-lyon.fr/spip/spip.php?article35) |
| Contact | [article44](https://escal.edu.ac-lyon.fr/spip/spip.php?article44) |
| Erreur 404 | [article43](https://escal.edu.ac-lyon.fr/spip/spip.php?article43) |
| Forum d'article | [article41](https://escal.edu.ac-lyon.fr/spip/spip.php?article41) |
| Le forum du site | [article360](https://escal.edu.ac-lyon.fr/spip/spip.php?article360) |
| Mots-clés | [article132](https://escal.edu.ac-lyon.fr/spip/spip.php?article132) |
| Recherche | [article42](https://escal.edu.ac-lyon.fr/spip/spip.php?article42) |
| Trombinoscope auteurs | [article202](https://escal.edu.ac-lyon.fr/spip/spip.php?article202) |

## Astuces (recettes de personnalisation)

| Recette | Article |
|---|---|
| Changer la couleur d'un seul bloc latéral | [article263](https://escal.edu.ac-lyon.fr/spip/spip.php?article263) |
| Exporter sa configuration d'Escal | [article286](https://escal.edu.ac-lyon.fr/spip/spip.php?article286) |
| Mettre une image de fond pour le bandeau | [article264](https://escal.edu.ac-lyon.fr/spip/spip.php?article264) |
| Modifier le fond d'une rubrique ou d'un article | [article267](https://escal.edu.ac-lyon.fr/spip/spip.php?article267) |
| Supprimer un élément | [article259](https://escal.edu.ac-lyon.fr/spip/spip.php?article259) |
| Remplacer une image d'Escal | [article334](https://escal.edu.ac-lyon.fr/spip/spip.php?article334) |
| Mettre un formulaire SPIP dans une noisette | [article266](https://escal.edu.ac-lyon.fr/spip/spip.php?article266) |
| Proposer aux visiteurs de choisir leur layout | [article282](https://escal.edu.ac-lyon.fr/spip/spip.php?article282) |
| Un graphisme en fonction des saisons | [article440](https://escal.edu.ac-lyon.fr/spip/spip.php?article440) |
| Utiliser une autre police de caractères | [article294](https://escal.edu.ac-lyon.fr/spip/spip.php?article294) |
| Afficher une carte OpenStreetMap | [article434](https://escal.edu.ac-lyon.fr/spip/spip.php?article434) |
| Limiter la taille des téléversements | [article260](https://escal.edu.ac-lyon.fr/spip/spip.php?article260) |

## Plugins compagnons recommandés par l'auteur

Not dependencies — suggestions from the doc site: Agenda · odt2spip · Crayons · NoSPAM · Formidable ·
Dépublie · Enluminures Typographiques · cibloc · Sommaire automatique · Blocs dépliables · pdf.js ·
Galleria · Diapo/Mediabox · Lecteur multimédia · Accès Restreint · Tri des articles par rubrique ·
SpiPDF · Rainette · Mailcrypt · Recherche FullText · Shoutbox.

Check `paquet.xml` for what Escal actually requires (`<necessite>`) versus merely detects (`<utilise>`).
