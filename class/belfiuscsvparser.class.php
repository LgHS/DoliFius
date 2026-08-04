<?php
/**
 * Parse et valide un export CSV Belfius.
 *
 * Principe directeur : échec bruyant et bloquant, jamais d'import silencieux dégradé.
 * Ne modifie jamais la base : produit uniquement un résultat structuré (lignes valides /
 * rejetées / rapport de cohérence) que l'appelant (BelfiusImport) utilisera après
 * confirmation explicite de l'utilisateur.
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

	/** @var string[] Erreurs bloquantes (ex: en-tête introuvable) */
	public $errors = array();

	/** @var array Lignes de transaction valides, indexées par numéro de ligne source */
	public $validLines = array();

	/** @var array Lignes rejetées, avec la raison du rejet */
	public $rejectedLines = array();

	/** @var float|null Solde de clôture annoncé dans le préambule du fichier */
	public $announcedBalance = null;

	/** @var float Somme calculée à partir des lignes valides */
	public $computedBalance = 0.0;

	/**
	 * Parse un fichier CSV Belfius (encodage ISO-8859-1, séparateur ';').
	 *
	 * @param string $filepath Chemin du fichier CSV uploadé
	 * @return int 1 si le fichier a pu être parsé (avec ou sans lignes rejetées), -1 en cas d'échec bloquant
	 */
	public function parse($filepath)
	{
		// TODO:
		// 1. Lire le fichier et convertir ISO-8859-1 -> UTF-8
		// 2. Extraire le solde de clôture annoncé depuis le bloc préambule
		// 3. Localiser la ligne d'en-tête via detectHeaderLine()
		// 4. Valider chaque ligne de transaction via validateLine()
		// 5. Calculer la cohérence globale via checkGlobalConsistency()
		$this->errors[] = 'Parser non implémenté';

		return -1;
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
		// TODO: comparer chaque ligne découpée sur ';' à self::EXPECTED_HEADER
		return -1;
	}

	/**
	 * Valide une ligne de transaction : nombre de colonnes, format de date, format de montant.
	 *
	 * @param string[] $row        Colonnes de la ligne
	 * @param int      $lineNumber Numéro de ligne dans le fichier source (pour le rapport)
	 * @return bool True si la ligne est valide
	 */
	protected function validateLine(array $row, $lineNumber)
	{
		// TODO: vérifier count($row) === self::NB_COLONNES_ATTENDUES,
		// regex date JJ/MM/AAAA, regex montant -?\d{1,3}(\.\d{3})*,\d{2}
		return false;
	}

	/**
	 * Compare la somme des montants des lignes valides au solde de clôture annoncé.
	 * Tout écart significatif (> quelques centimes) doit être remonté dans le rapport.
	 *
	 * @return bool True si le solde recalculé correspond au solde annoncé
	 */
	protected function checkGlobalConsistency()
	{
		// TODO
		return false;
	}
}
