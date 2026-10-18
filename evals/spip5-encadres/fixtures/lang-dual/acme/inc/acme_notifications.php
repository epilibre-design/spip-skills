<?php

if (!defined('_ECRIRE_INC_VERSION')) {
	return;
}

/**
 * Prévient le webmestre qu'un article vient d'être proposé à la publication.
 */
function acme_notifier_webmestre(int $id_article): void {
	$titre = sql_getfetsel('titre', 'spip_articles', 'id_article=' . $id_article);
	$sujet = _T('acme:email_proposition_sujet');
	$corps = _T('acme:email_proposition_corps', ['titre' => $titre], 'titre_article');

	$envoyer_mail = charger_fonction('envoyer_mail', 'inc');
	$envoyer_mail($GLOBALS['meta']['email_webmaster'], $sujet, $corps);
}
