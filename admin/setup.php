<?php
/**
 * Configuration du module Import Bancaire Belfius : choix du compte bancaire
 * Dolibarr cible pour l'import.
 */

// Le module peut être déployé dans htdocs/<module>/admin/ (2 niveaux) ou
// htdocs/custom/<module>/admin/ (3 niveaux) : on teste plusieurs profondeurs.
$res = 0;
if (!$res && file_exists("../../main.inc.php")) {
	$res = @include "../../main.inc.php";
}
if (!$res && file_exists("../../../main.inc.php")) {
	$res = @include "../../../main.inc.php";
}
if (!$res && file_exists("../../../../main.inc.php")) {
	$res = @include "../../../../main.inc.php";
}
if (!$res) {
	die("Include of main fails");
}

require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/bank.lib.php';

global $db, $langs, $user, $conf;

$langs->loadLangs(array("admin", "banks", "importbancairebelfius@importbancairebelfius"));

if (!$user->admin) {
	accessforbidden();
}

$action = GETPOST('action', 'aZ09');

/*
 * Actions
 */

if ($action == 'update') {
	$fk_account = GETPOSTINT('fk_account');
	$result = dolibarr_set_const($db, 'IMPORTBANCAIREBELFIUS_FK_ACCOUNT', $fk_account, 'chaine', 0, '', $conf->entity);

	if ($result > 0) {
		setEventMessages("Configuration enregistrée", null, 'mesgs');
	} else {
		setEventMessages($db->lasterror(), null, 'errors');
	}
}

/*
 * Affichage
 */

$title = $langs->trans("ImportBancaireBelfiusSetup");
llxHeader('', $title);

$linkback = '<a href="'.DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1">'.$langs->trans("BackToModuleList").'</a>';
print load_fiche_titre($title, $linkback, 'bank_account');

print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="update">';

print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><td>Paramètre</td><td>Valeur</td></tr>';

print '<tr class="oddeven">';
print '<td>Compte bancaire cible pour l\'import Belfius</td>';
print '<td>';

$currentAccount = !empty($conf->global->IMPORTBANCAIREBELFIUS_FK_ACCOUNT) ? $conf->global->IMPORTBANCAIREBELFIUS_FK_ACCOUNT : 0;

$sql = "SELECT rowid, label FROM ".MAIN_DB_PREFIX."bank_account";
$sql .= " WHERE entity = ".((int) $conf->entity);
$sql .= " ORDER BY label";

$resql = $db->query($sql);
if (!$resql) {
	print '<span class="error">Erreur SQL : '.dol_escape_htmltag($db->lasterror()).'</span>';
} else {
	print '<select name="fk_account" class="flat">';
	print '<option value="0">-- Sélectionner un compte --</option>';

	$nbAccounts = 0;
	while ($obj = $db->fetch_object($resql)) {
		$nbAccounts++;
		$selectedAttr = ($obj->rowid == $currentAccount) ? ' selected' : '';
		print '<option value="'.((int) $obj->rowid).'"'.$selectedAttr.'>'.dol_escape_htmltag($obj->label).'</option>';
	}

	print '</select>';

	if ($nbAccounts === 0) {
		print '<br><span class="warning">Aucun compte bancaire trouvé pour cette entité — vérifie qu\'un compte existe dans Banque & Caisse.</span>';
	}
}

print '</td>';
print '</tr>';

print '</table>';

print '<div class="center"><br><input type="submit" class="button button-save" value="Enregistrer"></div>';
print '</form>';

llxFooter();
$db->close();
