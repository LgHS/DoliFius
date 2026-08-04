<?php
/**
 * Descripteur du module Import Bancaire Belfius.
 * Déclare les permissions, le menu et la configuration du module.
 */

include_once DOL_DOCUMENT_ROOT.'/core/modules/DolibarrModules.class.php';

class modImportBancaireBelfius extends DolibarrModules
{
	/**
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		global $langs, $conf;

		$this->db = $db;

		// Identifiant unique du module (plage réservée aux modules externes/custom : >= 100000)
		$this->numero = 500000;

		// Nom technique utilisé pour $user->rights->importbancairebelfius->...
		$this->rights_class = 'importbancairebelfius';

		$this->family = "financial";
		$this->module_position = '90';

		$this->name = preg_replace('/^mod/i', '', get_class($this));
		$this->description = "Import des relevés bancaires Belfius (CSV) dans Dolibarr";
		$this->descriptionlong = "Importe les extraits de compte Belfius exportés au format CSV, valide strictement leur contenu (en-tête, lignes, cohérence du solde) et crée les écritures bancaires après confirmation de l'utilisateur.";

		$this->editor_name = 'DoliFius';
		$this->editor_url = '';

		$this->version = '1.0.0';

		$this->const_name = 'MAIN_MODULE_'.strtoupper($this->name);

		// Icône du module (picto core Dolibarr, pas d'image custom pour la V1)
		$this->picto = 'bank_account';

		$this->module_parts = array(
			'triggers' => 0,
			'login' => 0,
			'substitutions' => 0,
			'menus' => 0,
			'theme' => 0,
			'tpl' => 0,
			'barcode' => 0,
			'models' => 0,
			'css' => array(),
			'js' => array(),
			'hooks' => array(),
			'moduleforexternal' => 0,
		);

		$this->dirs = array();

		// Page de configuration du module
		$this->config_page_url = array("setup.php@importbancairebelfius");

		$this->hidden = false;

		// Dépend du module Banque/Compte financier du cœur Dolibarr
		$this->depends = array('modBanque');
		$this->requiredby = array();
		$this->conflictwith = array();

		$this->langfiles = array("importbancairebelfius@importbancairebelfius");

		$this->phpmin = array(7, 2);
		$this->need_dolibarr_version = array(23, -3);

		$this->warnings_activation = array();
		$this->warnings_activation_ext = array();

		// Constantes de configuration (ex: compte bancaire cible) - définies via admin/setup.php
		$this->const = array();

		// Pas de widgets pour la V1
		$this->boxes = array();

		// Permissions
		$this->rights = array();
		$r = 0;

		$this->rights[$r][0] = $this->numero + 1;
		$this->rights[$r][1] = "Consulter les imports bancaires Belfius";
		$this->rights[$r][4] = 'lire';
		$this->rights[$r][5] = 'read';
		$r++;

		$this->rights[$r][0] = $this->numero + 2;
		$this->rights[$r][1] = "Importer des relevés bancaires Belfius (créer les écritures)";
		$this->rights[$r][4] = 'ecrire';
		$this->rights[$r][5] = 'write';
		$r++;

		// Menus
		$this->menu = array();
		$r = 0;

		$this->menu[$r] = array(
			'fk_menu' => 'fk_mainmenu=bank',
			'type' => 'left',
			'titre' => 'Import Belfius',
			'mainmenu' => 'bank',
			'leftmenu' => 'importbancairebelfius',
			'url' => '/importbancairebelfius/belfiusimport.php',
			'langs' => 'importbancairebelfius@importbancairebelfius',
			'position' => 100,
			'enabled' => '1',
			'perms' => '$user->rights->importbancairebelfius->read',
			'target' => '',
			'user' => 0,
		);
		$r++;
	}

	/**
	 * Activation du module.
	 *
	 * @param string $options Options
	 * @return int 1 si OK, 0 si KO
	 */
	public function init($options = '')
	{
		$sql = array();

		return $this->_init($sql, $options);
	}

	/**
	 * Désactivation du module.
	 *
	 * @param string $options Options
	 * @return int 1 si OK, 0 si KO
	 */
	public function remove($options = '')
	{
		$sql = array();

		return $this->_remove($sql, $options);
	}
}
