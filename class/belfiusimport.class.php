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
	 * @param int $fk_account ID du compte bancaire Dolibarr cible
	 * @param User $user      Utilisateur effectuant l'import
	 * @return int 1 si OK, -1 en cas d'erreur
	 */
	public function import($fk_account, $user)
	{
		// TODO:
		// 1. Vérifier que $this->lastParseResult existe et provient bien d'une analyse validée
		// 2. Pour chaque ligne valide, vérifier l'absence de doublon (clé n° extrait + n° transaction)
		// 3. Créer les écritures via Account::addline() / AccountLine
		$this->errors[] = 'Import non implémenté';

		return -1;
	}
}
