<?php
/* Copyright (C) 2026 iooner.io for Liège Hackerspace
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

		// Identifiant unique du module. Plage 100000-499999 = éditeurs tiers (à réserver
		// officiellement auprès de l'équipe Dolibarr avant publication sur le Dolistore,
		// voir https://wiki.dolibarr.org/index.php?title=List_of_modules_id).
		// 109500 = choix provisoire, PAS ENCORE réservé officiellement.
		$this->numero = 109500;

		// Nom technique utilisé pour $user->rights->importbancairebelfius->...
		$this->rights_class = 'importbancairebelfius';

		$this->family = "financial";
		$this->module_position = '90';

		$this->name = preg_replace('/^mod/i', '', get_class($this));
		$this->description = "Import des relevés bancaires Belfius (CSV) dans Dolibarr";
		$this->descriptionlong = "Importe les extraits de compte Belfius exportés au format CSV, valide strictement leur contenu (en-tête, lignes, cohérence du solde) et crée les écritures bancaires après confirmation de l'utilisateur.";

		$this->editor_name = 'iooner for LgHS';
		$this->editor_url = 'https://github.com/LgHS/DoliFius';

		$this->version = '1.0.0';

		$this->const_name = 'MAIN_MODULE_'.strtoupper($this->name);

		// Icône du module (syntaxe "nom@techname" pour indiquer à Dolibarr de la chercher
		// dans le dossier du module). Deux fichiers fournis dans img/ pour couvrir les
		// différents contextes de rendu observés : importbancairebelfius.png (menu, titres
		// de page) et object_importbancairebelfius.png (liste des modules dans l'admin).
		$this->picto = 'importbancairebelfius@importbancairebelfius';

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
		// Instance de production encore en 22.0.2 au moment de l'écriture (migration vers 23.x
		// prévue mais pas encore faite) — le module doit s'activer dès maintenant sur 22.x.
		$this->need_dolibarr_version = array(22, -3);

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
		$this->rights[$r][4] = 'read';
		$r++;

		$this->rights[$r][0] = $this->numero + 2;
		$this->rights[$r][1] = "Importer des relevés bancaires Belfius (créer les écritures)";
		$this->rights[$r][4] = 'write';
		$r++;

		// Menus
		$this->menu = array();
		$r = 0;

		$this->menu[$r] = array(
			'fk_menu' => 'fk_mainmenu=bank',
			'type' => 'left',
			'titre' => 'Import Belfius',
			// Icône Font Awesome plutôt qu'une image custom, pour rester cohérent avec les
			// autres entrées du menu Banque & Caisse (qui utilisent toutes des picto FA).
			'prefix' => '<i class="fas fa-file-import pictofixedwidth"></i>',
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
