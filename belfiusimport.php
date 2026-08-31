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
 * Page d'import des relevés bancaires Belfius : upload du CSV, affichage du rapport
 * d'analyse (lignes acceptées / rejetées, cohérence du solde) et confirmation
 * explicite avant toute création d'écriture bancaire.
 */

// Le module peut être déployé dans htdocs/<module>/ (1 niveau) ou htdocs/custom/<module>/
// (2 niveaux) : on teste plusieurs profondeurs plutôt que de supposer un chemin fixe.
$res = 0;
if (!$res && file_exists("../main.inc.php")) {
	$res = @include "../main.inc.php";
}
if (!$res && file_exists("../../main.inc.php")) {
	$res = @include "../../main.inc.php";
}
if (!$res && file_exists("../../../main.inc.php")) {
	$res = @include "../../../main.inc.php";
}
if (!$res) {
	die("Include of main fails");
}

require_once DOL_DOCUMENT_ROOT.'/core/lib/bank.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
dol_include_once('/importbancairebelfius/class/belfiusimport.class.php');

global $db, $langs, $user, $conf;

$langs->loadLangs(array("banks", "importbancairebelfius@importbancairebelfius"));

// Sécurité
if (!$user->hasRight('importbancairebelfius', 'read')) {
	accessforbidden();
}

$action = GETPOST('action', 'aZ09');

// Fichier CSV en attente de confirmation, mémorisé en session le temps de la validation humaine
$sessionKey = 'BELFIUSIMPORT_PENDING_FILE';
$tmpDir = $conf->user->dir_temp;

/*
 * Actions
 */

if ($action == 'upload') {
	if (!$user->hasRight('importbancairebelfius', 'write')) {
		accessforbidden();
	}

	if (empty($_FILES['csvfile']['tmp_name']) || $_FILES['csvfile']['error'] != UPLOAD_ERR_OK) {
		setEventMessages($langs->trans("BelfiusErrorNoFileUploaded"), null, 'errors');
	} elseif (strtolower(pathinfo($_FILES['csvfile']['name'], PATHINFO_EXTENSION)) != 'csv') {
		setEventMessages($langs->trans("BelfiusErrorNotCsv"), null, 'errors');
	} else {
		if (!is_dir($tmpDir)) {
			dol_mkdir($tmpDir);
		}

		$destination = $tmpDir.'/belfiusimport_'.date('YmdHis').'_'.uniqid().'_'.dol_sanitizeFileName($_FILES['csvfile']['name']);
		$result = dol_move_uploaded_file($_FILES['csvfile']['tmp_name'], $destination, 1);

		if (!$result || preg_match('/^Error/', $result)) {
			setEventMessages($langs->trans("BelfiusErrorFileSaveFailed", $result), null, 'errors');
		} else {
			$_SESSION[$sessionKey] = $destination;
		}
	}

	header('Location: '.$_SERVER['PHP_SELF']);
	exit;
}

if ($action == 'cancel') {
	if (!empty($_SESSION[$sessionKey]) && file_exists($_SESSION[$sessionKey])) {
		dol_delete_file($_SESSION[$sessionKey]);
	}
	unset($_SESSION[$sessionKey]);

	header('Location: '.$_SERVER['PHP_SELF']);
	exit;
}

$import = new BelfiusImport($db);
$parser = null;

if (!empty($_SESSION[$sessionKey]) && file_exists($_SESSION[$sessionKey])) {
	$result = $import->analyze($_SESSION[$sessionKey]);
	if ($result < 0) {
		setEventMessages(implode(', ', $import->errors), null, 'errors');
		unset($_SESSION[$sessionKey]);
	} else {
		$parser = $import->lastParseResult;
	}
}

$lockKey = $sessionKey.'_LOCK';

if ($action == 'confirm' && $parser) {
	if (!$user->hasRight('importbancairebelfius', 'write')) {
		accessforbidden();
	}

	// Garde-fou contre un double-clic ou un rechargement malheureux qui enverrait deux
	// confirmations en parallèle avant que la première n'ait fini d'écrire en base.
	if (!empty($_SESSION[$lockKey])) {
		setEventMessages($langs->trans("BelfiusImportInProgress"), null, 'warnings');
	} else {
		$_SESSION[$lockKey] = 1;

		$fk_account = !empty($conf->global->IMPORTBANCAIREBELFIUS_FK_ACCOUNT) ? $conf->global->IMPORTBANCAIREBELFIUS_FK_ACCOUNT : 0;

		if (empty($fk_account)) {
			setEventMessages($langs->trans("BelfiusNoAccountConfigured"), null, 'errors');
			unset($_SESSION[$lockKey]);
		} else {
			$result = $import->import($fk_account, $user);
			unset($_SESSION[$lockKey]);

			if ($result < 0) {
				setEventMessages(implode(', ', $import->errors), null, 'errors');
			} else {
				setEventMessages($langs->trans("BelfiusImportResult", $import->importedCount, $import->skippedDuplicatesCount), null, 'mesgs');
				dol_delete_file($_SESSION[$sessionKey]);
				unset($_SESSION[$sessionKey]);
				$parser = null;
			}
		}
	}
}

/*
 * Affichage
 */

$title = $langs->trans("ImportBancaireBelfius");
llxHeader('', $title);

print '<div class="center"><img src="'.dol_buildpath('/importbancairebelfius/img/dolifiuslogo.png', 1).'" style="max-height:80px;" alt="DoliFius"></div>';

print load_fiche_titre($title, '', 'bank_account');

if (!$parser) {
	// Pas d'analyse en attente : formulaire d'upload
	print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'" enctype="multipart/form-data">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="upload">';
	print '<div class="center">';
	print '<input type="file" name="csvfile" accept=".csv" required> ';
	print '<input type="submit" class="button" value="'.dol_escape_htmltag($langs->trans("BelfiusAnalyzeFile")).'">';
	print '</div>';
	print '</form>';
} else {
	// Rapport d'analyse à valider avant toute écriture en base
	print '<div class="info">'.dol_escape_htmltag($langs->trans("BelfiusReportIntro")).'</div>';

	if (!empty($parser->warnings)) {
		foreach ($parser->warnings as $warning) {
			print '<div class="warning">'.dol_escape_htmltag($warning).'</div>';
		}
	}

	print '<table class="noborder centpercent">';
	print '<tr class="liste_titre"><td>'.$langs->trans("BelfiusIndicator").'</td><td>'.$langs->trans("BelfiusValue").'</td></tr>';
	print '<tr class="oddeven"><td>'.$langs->trans("BelfiusValidLines").'</td><td>'.count($parser->validLines).'</td></tr>';
	print '<tr class="oddeven"><td>'.$langs->trans("BelfiusRejectedLines").'</td><td>'.count($parser->rejectedLines).'</td></tr>';
	print '<tr class="oddeven"><td>'.$langs->trans("BelfiusComputedBalance").'</td><td>'.price($parser->computedBalance).'</td></tr>';
	print '<tr class="oddeven"><td>'.$langs->trans("BelfiusAnnouncedBalance").'</td><td>'.($parser->announcedBalance !== null ? price($parser->announcedBalance) : '-').'</td></tr>';
	print '</table>';

	print '<br><h3>'.$langs->trans("BelfiusRejectedLines").' ('.count($parser->rejectedLines).')</h3>';
	print '<table class="noborder centpercent">';
	print '<tr class="liste_titre"><td>'.$langs->trans("BelfiusLineNumber").'</td><td>'.$langs->trans("BelfiusRejectReason").'</td></tr>';
	if (empty($parser->rejectedLines)) {
		print '<tr class="oddeven"><td colspan="2" class="opacitymedium">'.$langs->trans("BelfiusNoRejectedLines").'</td></tr>';
	} else {
		foreach ($parser->rejectedLines as $lineNumber => $info) {
			print '<tr class="oddeven"><td>'.((int) $lineNumber).'</td><td>'.dol_escape_htmltag($info['reason']).'</td></tr>';
		}
	}
	print '</table>';

	if (!empty($parser->validLines)) {
		print '<br><h3>'.$langs->trans("BelfiusLinesToImport").' ('.count($parser->validLines).')</h3>';
		print '<div style="max-height:500px; overflow-y:auto;">';
		print '<table class="noborder centpercent">';
		print '<tr class="liste_titre">';
		print '<td>'.$langs->trans("Date").'</td>';
		print '<td>'.$langs->trans("BelfiusCounterparty").'</td>';
		print '<td class="right">'.$langs->trans("Amount").'</td>';
		print '<td>'.$langs->trans("BelfiusCommunication").'</td>';
		print '</tr>';

		foreach ($parser->validLines as $lineNumber => $row) {
			$date = $row[BelfiusCsvParser::COL_DATE_COMPTA];
			$contrepartie = trim($row[5]) !== '' ? $row[5] : $row[8]; // fallback sur "Transaction" si pas de contrepartie (ex. paiement carte)
			$montant = (float) str_replace(',', '.', $row[BelfiusCsvParser::COL_MONTANT]);
			$communication = $row[14];

			$colorStyle = $montant < 0 ? 'color:#c00;' : 'color:#008000;';

			print '<tr class="oddeven">';
			print '<td class="nowraponall">'.dol_escape_htmltag($date).'</td>';
			print '<td>'.dol_escape_htmltag($contrepartie).'</td>';
			print '<td class="right nowraponall" style="'.$colorStyle.'">'.price($montant).' €</td>';
			print '<td>'.dol_escape_htmltag(dol_trunc($communication, 60)).'</td>';
			print '</tr>';
		}

		print '</table>';
		print '</div>';
	}

	$fk_account = !empty($conf->global->IMPORTBANCAIREBELFIUS_FK_ACCOUNT) ? $conf->global->IMPORTBANCAIREBELFIUS_FK_ACCOUNT : 0;
	if (empty($fk_account)) {
		print '<br><div class="warning">'.dol_escape_htmltag($langs->trans("BelfiusNoAccountConfiguredWarning")).'</div>';
	} else {
		$targetAccount = new Account($db);
		$targetAccount->fetch($fk_account);
		print '<br><div class="center">'.$langs->trans("BelfiusTargetAccount").' : <strong>'.dol_escape_htmltag($targetAccount->label).'</strong></div>';
	}

	print '<br><form method="POST" action="'.$_SERVER['PHP_SELF'].'" id="belfius_report_form">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<div class="center">';
	print '<input type="submit" class="button" id="btn_belfius_cancel" name="action_cancel" formaction="'.$_SERVER['PHP_SELF'].'?action=cancel" value="'.dol_escape_htmltag($langs->trans("Cancel")).'">';
	print ' <input type="submit" class="button button-save" id="btn_belfius_confirm" formaction="'.$_SERVER['PHP_SELF'].'?action=confirm" value="'.dol_escape_htmltag($langs->trans("BelfiusConfirmImport")).'"'.(empty($fk_account) ? ' disabled' : '').'>';
	print '</div>';
	print '</form>';
	print '<script>
document.getElementById("belfius_report_form").addEventListener("submit", function (e) {
	document.getElementById("btn_belfius_cancel").disabled = true;
	document.getElementById("btn_belfius_confirm").disabled = true;
	if (e.submitter && e.submitter.id === "btn_belfius_confirm") {
		e.submitter.value = '.json_encode($langs->trans("BelfiusImportInProgressButton")).';
	}
});
</script>';
}

llxFooter();
$db->close();
