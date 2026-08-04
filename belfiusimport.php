<?php
/**
 * Page d'import des relevés bancaires Belfius : upload du CSV, affichage du rapport
 * d'analyse (lignes acceptées / rejetées, cohérence du solde) et confirmation
 * explicite avant toute création d'écriture bancaire.
 */

require '../main.inc.php';

require_once DOL_DOCUMENT_ROOT.'/core/lib/bank.lib.php';
dol_include_once('/importbancairebelfius/class/belfiusimport.class.php');

global $db, $langs, $user;

$langs->loadLangs(array("banks", "importbancairebelfius@importbancairebelfius"));

// Sécurité
if (!$user->hasRight('importbancairebelfius', 'read')) {
	accessforbidden();
}

$action = GETPOST('action', 'aZ09');

/*
 * Actions
 */

// TODO:
// - action 'upload' : enregistrer le fichier envoyé, appeler BelfiusImport::analyze(),
//   stocker le résultat en session dans l'attente de la confirmation
// - action 'confirm' : vérifier $user->hasRight('importbancairebelfius', 'write'),
//   appeler BelfiusImport::import() sur le résultat d'analyse précédemment stocké

/*
 * Affichage
 */

$title = $langs->trans("ImportBancaireBelfius");
llxHeader('', $title);

print load_fiche_titre($title, '', 'bank_account');

// TODO: formulaire d'upload du CSV (si pas d'analyse en attente)
// TODO: affichage du rapport (lignes lues / acceptées / rejetées + raisons, solde
//       recalculé vs solde annoncé) et bouton de confirmation (si une analyse est en attente)

llxFooter();
$db->close();
