<?php
require_once DOL_DOCUMENT_ROOT.'/compta/bank/class/account.class.php';
dol_include_once('/importbancairebelfius/class/belfiuscsvparser.class.php');

/**
 * Orchestration de l'import bancaire Belfius.
 *
 * Appelle BelfiusCsvParser pour analyser le CSV, expose un rapport à valider par
 * l'utilisateur, et ne crée les écritures bancaires (via les classes core Account /
 * AccountLine) qu'après confirmation explicite. Aucune écriture n'est créée pendant
 * l'étape d'analyse.
 */
class BelfiusImport
{
	/** @var DoliDB */
	protected $db;

	/** @var string[] */
	public $errors = array();

	/** @var BelfiusCsvParser|null Résultat de la dernière analyse */
	public $lastParseResult = null;

	/** @var int Nombre d'écritures effectivement créées lors du dernier import() */
	public $importedCount = 0;

	/** @var int Nombre de lignes ignorées car déjà importées (doublon détecté) */
	public $skippedDuplicatesCount = 0;

	/**
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		$this->db = $db;
	}

	/**
	 * Étape 1 : analyse le fichier uploadé et prépare le rapport (lignes lues / acceptées /
	 * rejetées + raisons, solde recalculé vs solde annoncé). N'écrit rien en base.
	 *
	 * @param string $filepath Chemin du fichier CSV uploadé
	 * @return int 1 si l'analyse a pu être menée, -1 en cas d'échec bloquant
	 */
	public function analyze($filepath)
	{
		$parser = new BelfiusCsvParser();
		$result = $parser->parse($filepath);

		if ($result < 0) {
			$this->errors = $parser->errors;
			return -1;
		}

		$this->lastParseResult = $parser;

		return 1;
	}

	/**
	 * Étape 2 : crée les écritures bancaires pour les lignes valides d'une analyse déjà
	 * confirmée par l'utilisateur. Ne doit jamais être appelée sans confirmation explicite.
	 *
	 * Déduplication : la clé naturelle "<extrait>-<transaction>" (validée sur données réelles,
	 * voir CLAUDE.md) est stockée dans le champ "N° chèque" de l'écriture (réutilisé comme champ
	 * technique libre, non affiché en avant du libellé). Avant de créer une ligne, on vérifie
	 * qu'aucune écriture existante sur ce compte ne porte déjà cette clé — ça permet de
	 * réimporter un export qui chevaucherait un import précédent sans créer de doublons, sans
	 * avoir besoin d'une table dédiée.
	 *
	 * Tout ou rien : si une ligne échoue en cours de route, toute la transaction est annulée
	 * plutôt que de laisser un import partiel.
	 *
	 * @param int  $fk_account ID du compte bancaire Dolibarr cible
	 * @param User $user       Utilisateur effectuant l'import
	 * @return int 1 si OK, -1 en cas d'erreur
	 */
	public function import($fk_account, $user)
	{
		$this->errors = array();
		$this->importedCount = 0;
		$this->skippedDuplicatesCount = 0;

		if (!$this->lastParseResult || empty($this->lastParseResult->validLines)) {
			$this->errors[] = "Aucune analyse valide en attente d'import";
			return -1;
		}

		if (empty($fk_account)) {
			$this->errors[] = "Aucun compte bancaire cible sélectionné";
			return -1;
		}

		$account = new Account($this->db);
		if ($account->fetch($fk_account) <= 0) {
			$this->errors[] = "Compte bancaire introuvable (id ".((int) $fk_account).")";
			return -1;
		}

		$this->db->begin();

		foreach ($this->lastParseResult->validLines as $lineNumber => $row) {
			$numExtrait = $row[BelfiusCsvParser::COL_NUM_EXTRAIT];
			$numTransaction = $row[BelfiusCsvParser::COL_NUM_TRANSACTION];
			$amount = (float) str_replace(',', '.', $row[BelfiusCsvParser::COL_MONTANT]);
			// Fallback sur "Transaction" si pas de nom de contrepartie (ex. paiement carte)
			$contrepartie = trim($row[5]) !== '' ? $row[5] : $row[8];
			$communication = trim($row[14]);

			$dateCompta = $this->parseDolDate($row[BelfiusCsvParser::COL_DATE_COMPTA]);
			$dateValeur = $this->parseDolDate($row[BelfiusCsvParser::COL_DATE_VALEUR]);

			$dedupKey = $numExtrait.'-'.$numTransaction;
			$label = trim($contrepartie.($communication !== '' ? ' - '.$communication : ''));
			$label = dol_trunc($label, 250, 'right', 'UTF-8', 1);

			if ($this->isDuplicate($fk_account, $dedupKey)) {
				$this->skippedDuplicatesCount++;
				continue;
			}

			// Type d'opération générique : pas d'info fiable dans le CSV pour distinguer
			// virement/carte/prélèvement, on se base uniquement sur le sens du montant.
			$oper = ($amount >= 0) ? 'VIR' : 'PRE';

			$result = $account->addline($dateCompta, $oper, $label, $amount, $dedupKey, 0, $user, '', '', '', $dateValeur);

			if ($result <= 0) {
				$this->db->rollback();
				$this->errors[] = "Échec de création de l'écriture pour la ligne ".((int) $lineNumber)." (extrait ".$numExtrait."/".$numTransaction.") : ".$account->error;
				return -1;
			}

			$this->importedCount++;
		}

		$this->db->commit();

		return 1;
	}

	/**
	 * Vérifie si une écriture portant déjà cette clé de déduplication existe sur ce compte.
	 *
	 * @param int    $fk_account ID du compte bancaire
	 * @param string $dedupKey   Clé "<extrait>-<transaction>"
	 * @return bool True si une écriture avec cette clé existe déjà
	 */
	protected function isDuplicate($fk_account, $dedupKey)
	{
		$sql = "SELECT COUNT(*) as nb FROM ".MAIN_DB_PREFIX."bank";
		$sql .= " WHERE fk_account = ".((int) $fk_account);
		$sql .= " AND num_chq = '".$this->db->escape($dedupKey)."'";

		$resql = $this->db->query($sql);
		if (!$resql) {
			return false;
		}

		$obj = $this->db->fetch_object($resql);

		return $obj && $obj->nb > 0;
	}

	/**
	 * Convertit une date JJ/MM/AAAA du CSV en timestamp Dolibarr.
	 *
	 * @param string $frenchDate Date au format JJ/MM/AAAA
	 * @return int Timestamp
	 */
	protected function parseDolDate($frenchDate)
	{
		list($day, $month, $year) = explode('/', $frenchDate);

		return dol_mktime(0, 0, 0, (int) $month, (int) $day, (int) $year);
	}
}
