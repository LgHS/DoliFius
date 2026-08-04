<?php
/**
 * Configuration du module Import Bancaire Belfius : choix du compte bancaire
 * Dolibarr cible pour l'import.
 */

require '../../main.inc.php';

require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/bank.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formbank.class.php';

global $db, $langs, $user;

$langs->loadLangs(array("admin", "banks", "importbancairebelfius@importbancairebelfius"));

if (!$user->admin) {
	accessforbidden();
}

$action = GETPOST('action', 'aZ09');

/*
 * Actions
 */

if ($action == 'update') {
	// TODO: dolibarr_set_const($db, 'IMPORTBANCAIREBELFIUS_FK_ACCOUNT', GETPOSTINT('fk_account'), 'chaine', 0, '', $conf->entity);
}

/*
 * Affichage
 */

$title = $langs->trans("ImportBancaireBelfiusSetup");
llxHeader('', $title);

$linkback = '<a href="'.($backtopage ?? DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1').'">'.$langs->trans("BackToModuleList").'</a>';
print load_fiche_titre($title, $linkback, 'bank_account');

// TODO: formulaire de sélection du compte bancaire Dolibarr cible
// (form_bank_account($db, GETPOSTINT('fk_account'), 'fk_account', '', 1) via FormBank)

llxFooter();
$db->close();
