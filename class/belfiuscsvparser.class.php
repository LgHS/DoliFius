<?php
/**
 * Parse et valide un export CSV Belfius.
 *
 * Principe directeur : échec bruyant et bloquant, jamais d'import silencieux dégradé.
 * Ne modifie jamais la base : produit uniquement un résultat structuré (lignes valides /
 * rejetées / rapport de cohérence) que l'appelant (BelfiusImport) utilisera après
 * confirmation explicite de l'utilisateur.
 *
 * Ne dépend d'aucune classe Dolibarr : logique pure, testable en CLI.
 */
class BelfiusCsvParser
{
	/**
	 * En-tête attendu, dans l'ordre exact. Toute correspondance partielle ou décalée
	 * est considérée comme un changement de format côté Belfius.
	 */
	const EXPECTED_HEADER = array(
		'Compte',
		'Date de comptabilisation',
		"Numéro d'extrait",
		'Numéro de transaction',
		'Compte contrepartie',
		'Nom contrepartie contient',
		'Rue et numéro',
		'Code postal et localité',
		'Transaction',
		'Date valeur',
		'Montant',
		'Devise',
		'BIC',
		'Code pays',
		'Communications',
	);

	const NB_COLONNES_ATTENDUES = 15;

	// Index (0-based) des colonnes utilisées pour la validation / le calcul de cohérence
	const COL_DATE_COMPTA = 1;
	const COL_NUM_EXTRAIT = 2;
	const COL_NUM_TRANSACTION = 3;
	const COL_DATE_VALEUR = 9;
	const COL_MONTANT = 10;

	// Le montant n'a pas de séparateur de milliers dans cette colonne (vérifié sur exports réels :
	// "1200,00", jamais "1.200,00"). Ne pas réutiliser ce pattern pour le champ "Dernier solde" du
	// préambule, qui lui utilise le point comme séparateur de milliers.
	const REGEX_MONTANT = '/^-?\d+,\d{2}$/';
	const REGEX_DATE = '/^\d{2}\/\d{2}\/\d{4}$/';

	// Nombre max de lignes scannées pour trouver l'en-tête (le préambule observé fait ~12 lignes ;
	// large marge de sécurité sans pour autant scanner tout le fichier si l'en-tête est introuvable)
	const MAX_LIGNES_PREAMBULE = 50;

	/** @var string[] Erreurs bloquantes (ex: en-tête introuvable) — si non vide, parse() a retourné -1 */
	public $errors = array();

	/** @var string[] Avertissements non bloquants (ex: écart de solde) — n'empêchent pas l'import */
	public $warnings = array();

	/** @var array<int, string[]> Lignes de transaction valides, indexées par numéro de ligne source (1-based) */
	public $validLines = array();

	/** @var array<int, array{row: string[], reason: string}> Lignes rejetées, avec la raison du rejet */
	public $rejectedLines = array();

	/** @var float|null Solde annoncé dans le préambule ("Dernier solde") */
	public $announcedBalance = null;

	/** @var string|null Date/heure du solde annoncé, telle que fournie dans le préambule */
	public $announcedBalanceDate = null;

	/** @var string|null Date de fin du filtre d'export ("Date de comptabilisation jusqu'au"), format JJ/MM/AAAA */
	public $filterDateTo = null;

	/** @var float Somme des montants des lignes valides */
	public $computedBalance = 0.0;

	/**
	 * Parse un fichier CSV Belfius (encodage ISO-8859-1, séparateur ';').
	 *
	 * @param string $filepath Chemin du fichier CSV uploadé
	 * @return int 1 si le fichier a pu être parsé (avec ou sans lignes rejetées), -1 en cas d'échec bloquant
	 */
	public function parse($filepath)
	{
		$this->errors = array();
		$this->warnings = array();
		$this->validLines = array();
		$this->rejectedLines = array();
		$this->announcedBalance = null;
		$this->announcedBalanceDate = null;
		$this->filterDateTo = null;
		$this->computedBalance = 0.0;

		if (!is_readable($filepath)) {
			$this->errors[] = "Fichier illisible ou introuvable";
			return -1;
		}

		$raw = file_get_contents($filepath);
		if ($raw === false || $raw === '') {
			$this->errors[] = "Fichier vide ou illisible";
			return -1;
		}

		$content = @mb_convert_encoding($raw, 'UTF-8', 'ISO-8859-1');
		$content = str_replace("\r\n", "\n", $content);
		$lines = explode("\n", $content);
		while (count($lines) > 0 && trim(end($lines)) === '') {
			array_pop($lines);
		}

		$headerIndex = $this->detectHeaderLine($lines);
		if ($headerIndex < 0) {
			$this->errors[] = "En-tête introuvable dans les ".self::MAX_LIGNES_PREAMBULE." premières lignes : format possiblement modifié par Belfius";
			return -1;
		}

		$this->parsePreamble(array_slice($lines, 0, $headerIndex));

		for ($i = $headerIndex + 1; $i < count($lines); $i++) {
			$lineNumber = $i + 1; // 1-based, pour un rapport lisible par un humain
			$rawLine = $lines[$i];
			if (trim($rawLine) === '') {
				continue;
			}

			$row = explode(';', $rawLine);
			if ($this->validateLine($row, $lineNumber)) {
				$this->validLines[$lineNumber] = $row;
				$this->computedBalance += (float) str_replace(',', '.', $row[self::COL_MONTANT]);
			}
		}

		$this->computedBalance = round($this->computedBalance, 2);

		$this->checkGlobalConsistency();

		return 1;
	}

	/**
	 * Recherche, parmi les premières lignes du fichier, celle qui correspond exactement
	 * à EXPECTED_HEADER. Ne fait aucune hypothèse sur un nombre fixe de lignes de préambule.
	 *
	 * @param string[] $lines Lignes brutes du fichier (déjà converties en UTF-8)
	 * @return int Index de la ligne d'en-tête, ou -1 si introuvable
	 */
	protected function detectHeaderLine(array $lines)
	{
		$expected = implode(';', self::EXPECTED_HEADER);
		$limit = min(count($lines), self::MAX_LIGNES_PREAMBULE);

		for ($i = 0; $i < $limit; $i++) {
			if (trim($lines[$i]) === $expected) {
				return $i;
			}
		}

		return -1;
	}

	/**
	 * Extrait du bloc préambule les informations utiles : solde annoncé, date de ce solde,
	 * et date de fin du filtre d'export (nécessaire pour savoir si le contrôle de cohérence
	 * de solde est pertinent — voir checkGlobalConsistency()).
	 *
	 * @param string[] $preambleLines Lignes du préambule (avant la ligne d'en-tête)
	 * @return void
	 */
	protected function parsePreamble(array $preambleLines)
	{
		foreach ($preambleLines as $line) {
			$parts = explode(';', $line, 2);
			if (count($parts) < 2) {
				continue;
			}

			$key = trim($parts[0]);
			$value = trim($parts[1]);

			if ($value === '') {
				continue;
			}

			if ($key === "Date de comptabilisation jusqu'au") {
				$this->filterDateTo = $value;
			} elseif ($key === 'Dernier solde') {
				// Format observé : "16.275,02 EUR" — point = séparateur de milliers ici,
				// contrairement à la colonne Montant des transactions (voir REGEX_MONTANT).
				if (preg_match('/^(-?[0-9.]+),(\d{2})\s*([A-Z]{3})?$/', $value, $m)) {
					$integerPart = str_replace('.', '', $m[1]);
					$this->announcedBalance = (float) ($integerPart.'.'.$m[2]);
				}
			} elseif ($key === 'Date/heure du dernier solde') {
				$this->announcedBalanceDate = $value;
			}
		}
	}

	/**
	 * Valide une ligne de transaction : nombre de colonnes, format des dates, format du montant.
	 *
	 * @param string[] $row        Colonnes de la ligne
	 * @param int      $lineNumber Numéro de ligne dans le fichier source (pour le rapport)
	 * @return bool True si la ligne est valide
	 */
	protected function validateLine(array $row, $lineNumber)
	{
		if (count($row) !== self::NB_COLONNES_ATTENDUES) {
			$this->rejectedLines[$lineNumber] = array(
				'row' => $row,
				'reason' => 'Nombre de colonnes invalide ('.count($row).' au lieu de '.self::NB_COLONNES_ATTENDUES.')',
			);
			return false;
		}

		if (!preg_match(self::REGEX_DATE, $row[self::COL_DATE_COMPTA])) {
			$this->rejectedLines[$lineNumber] = array(
				'row' => $row,
				'reason' => 'Date de comptabilisation invalide : "'.$row[self::COL_DATE_COMPTA].'"',
			);
			return false;
		}

		if (!preg_match(self::REGEX_DATE, $row[self::COL_DATE_VALEUR])) {
			$this->rejectedLines[$lineNumber] = array(
				'row' => $row,
				'reason' => 'Date valeur invalide : "'.$row[self::COL_DATE_VALEUR].'"',
			);
			return false;
		}

		if (!preg_match(self::REGEX_MONTANT, $row[self::COL_MONTANT])) {
			$this->rejectedLines[$lineNumber] = array(
				'row' => $row,
				'reason' => 'Montant invalide : "'.$row[self::COL_MONTANT].'"',
			);
			return false;
		}

		return true;
	}

	/**
	 * Compare la somme des montants des lignes valides au solde annoncé dans le préambule.
	 *
	 * Ce contrôle n'a de sens que si l'export couvre la période jusqu'à aujourd'hui : le champ
	 * "Dernier solde" Belfius reflète le solde *live* du compte au moment de l'export, pas le
	 * solde de clôture de la période filtrée (vérifié sur des exports réels : plusieurs exports
	 * avec des plages de dates différentes affichaient le même "Dernier solde"). Résultat toujours
	 * traité comme un avertissement non bloquant, jamais comme une erreur qui interrompt le parse.
	 *
	 * @return void
	 */
	protected function checkGlobalConsistency()
	{
		if ($this->announcedBalance === null) {
			$this->warnings[] = "Solde annoncé introuvable dans le préambule : contrôle de cohérence ignoré";
			return;
		}

		$today = date('d/m/Y');
		if ($this->filterDateTo !== $today) {
			$this->warnings[] = "Contrôle de cohérence de solde non applicable : la période exportée s'arrête au ".$this->filterDateTo.", pas à aujourd'hui (le \"Dernier solde\" Belfius est le solde live du compte, pas celui de la période)";
			return;
		}

		$diff = round($this->computedBalance - $this->announcedBalance, 2);
		if (abs($diff) > 0.05) {
			$this->warnings[] = "Écart de cohérence : solde recalculé ".number_format($this->computedBalance, 2, ',', '.')." € vs solde annoncé ".number_format($this->announcedBalance, 2, ',', '.')." € (écart ".number_format($diff, 2, ',', '.')." €)";
		}
	}
}
