<?php
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

global $db, $langs, $user;

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
		setEventMessages("Aucun fichier reçu ou erreur d'upload", null, 'errors');
	} elseif (strtolower(pathinfo($_FILES['csvfile']['name'], PATHINFO_EXTENSION)) != 'csv') {
		setEventMessages("Le fichier doit être un .csv", null, 'errors');
	} else {
		if (!is_dir($tmpDir)) {
			dol_mkdir($tmpDir);
		}

		$destination = $tmpDir.'/belfiusimport_'.date('YmdHis').'_'.uniqid().'_'.dol_sanitizeFileName($_FILES['csvfile']['name']);
		$result = dol_move_uploaded_file($_FILES['csvfile']['tmp_name'], $destination, 1);

		if (!$result || preg_match('/^Error/', $result)) {
			setEventMessages("Échec de l'enregistrement du fichier uploadé : ".$result, null, 'errors');
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

if ($action == 'confirm' && $parser) {
	if (!$user->hasRight('importbancairebelfius', 'write')) {
		accessforbidden();
	}

	$fk_account = GETPOSTINT('fk_account');
	$result = $import->import($fk_account, $user);
	if ($result < 0) {
		setEventMessages(implode(', ', $import->errors), null, 'errors');
	} else {
		setEventMessages("Import terminé", null, 'mesgs');
		dol_delete_file($_SESSION[$sessionKey]);
		unset($_SESSION[$sessionKey]);
		$parser = null;
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
	print '<input type="submit" class="button" value="Analyser le fichier">';
	print '</div>';
	print '</form>';
} else {
	// Rapport d'analyse à valider avant toute écriture en base
	print '<div class="info">Aucune écriture n\'a été créée en base : le rapport ci-dessous est une analyse à valider.</div>';

	if (!empty($parser->warnings)) {
		foreach ($parser->warnings as $warning) {
			print '<div class="warning">'.dol_escape_htmltag($warning).'</div>';
		}
	}

	print '<table class="noborder centpercent">';
	print '<tr class="liste_titre"><td>Indicateur</td><td>Valeur</td></tr>';
	print '<tr class="oddeven"><td>Lignes valides</td><td>'.count($parser->validLines).'</td></tr>';
	print '<tr class="oddeven"><td>Lignes rejetées</td><td>'.count($parser->rejectedLines).'</td></tr>';
	print '<tr class="oddeven"><td>Solde recalculé</td><td>'.price($parser->computedBalance).'</td></tr>';
	print '<tr class="oddeven"><td>Solde annoncé (préambule)</td><td>'.($parser->announcedBalance !== null ? price($parser->announcedBalance) : '-').'</td></tr>';
	print '</table>';

	print '<br><h3>Lignes rejetées ('.count($parser->rejectedLines).')</h3>';
	print '<table class="noborder centpercent">';
	print '<tr class="liste_titre"><td>Ligne</td><td>Raison du rejet</td></tr>';
	if (empty($parser->rejectedLines)) {
		print '<tr class="oddeven"><td colspan="2" class="opacitymedium">Aucune ligne rejetée</td></tr>';
	} else {
		foreach ($parser->rejectedLines as $lineNumber => $info) {
			print '<tr class="oddeven"><td>'.((int) $lineNumber).'</td><td>'.dol_escape_htmltag($info['reason']).'</td></tr>';
		}
	}
	print '</table>';

	if (!empty($parser->validLines)) {
		print '<br><h3>Lignes qui seront importées ('.count($parser->validLines).')</h3>';
		print '<div style="max-height:500px; overflow-y:auto;">';
		print '<table class="noborder centpercent">';
		print '<tr class="liste_titre">';
		print '<td>Date</td>';
		print '<td>Contrepartie</td>';
		print '<td class="right">Montant</td>';
		print '<td>Communication</td>';
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

	print '<br><form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<div class="center">';
	print '<input type="submit" class="button" name="action_cancel" formaction="'.$_SERVER['PHP_SELF'].'?action=cancel" value="Annuler">';
	print ' <input type="submit" class="button button-save" formaction="'.$_SERVER['PHP_SELF'].'?action=confirm" value="Confirmer l\'import">';
	print '</div>';
	print '</form>';
}

llxFooter();
$db->close();
