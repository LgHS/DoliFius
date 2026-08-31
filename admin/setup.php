<?php
/* Copyright (C) 2026 LgHS
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */
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
		setEventMessages($langs->trans("BelfiusConfigSaved"), null, 'mesgs');
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
print '<tr class="liste_titre"><td>'.$langs->trans("BelfiusParameter").'</td><td>'.$langs->trans("BelfiusValue").'</td></tr>';

print '<tr class="oddeven">';
print '<td>'.$langs->trans("BelfiusTargetAccountLabel").'</td>';
print '<td>';

$currentAccount = !empty($conf->global->IMPORTBANCAIREBELFIUS_FK_ACCOUNT) ? $conf->global->IMPORTBANCAIREBELFIUS_FK_ACCOUNT : 0;

$sql = "SELECT rowid, label FROM ".MAIN_DB_PREFIX."bank_account";
$sql .= " WHERE entity = ".((int) $conf->entity);
$sql .= " ORDER BY label";

$resql = $db->query($sql);
if (!$resql) {
	print '<span class="error">'.dol_escape_htmltag($langs->trans("BelfiusSqlError", $db->lasterror())).'</span>';
} else {
	print '<select name="fk_account" class="flat">';
	print '<option value="0">'.dol_escape_htmltag($langs->trans("BelfiusSelectAccount")).'</option>';

	$nbAccounts = 0;
	while ($obj = $db->fetch_object($resql)) {
		$nbAccounts++;
		$selectedAttr = ($obj->rowid == $currentAccount) ? ' selected' : '';
		print '<option value="'.((int) $obj->rowid).'"'.$selectedAttr.'>'.dol_escape_htmltag($obj->label).'</option>';
	}

	print '</select>';

	if ($nbAccounts === 0) {
		print '<br><span class="warning">'.dol_escape_htmltag($langs->trans("BelfiusNoAccountFound")).'</span>';
	}
}

print '</td>';
print '</tr>';

print '</table>';

print '<div class="center"><br><input type="submit" class="button button-save" value="'.dol_escape_htmltag($langs->trans("Save")).'"></div>';
print '</form>';

llxFooter();
$db->close();
