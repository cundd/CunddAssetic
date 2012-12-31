<?php
namespace Cundd\Assetic\Controller;

/*
 *  Copyright notice
 *
 *  (c) 2012 Daniel Corn <info@cundd.net>, cundd
 *
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 */

use Cundd\Assetic\Plugin;
\Tx_CunddComposer_Autoloader::register();

echo '<pre>';
debug_print_backtrace(NULL, 5);
echo '</pre>';


require_once(__DIR__ . '/../Plugin.php');
// if (!version_compare(TYPO3_version, '6.0.0', '>=')) {
// 	class_alias('Cundd\\Assetic\\Controller\\AssetController', 'Tx_Assetic_Controller_AssetController', FALSE);
// }

if (!class_exists('Cundd\\Assetic\\Controller\\AssetController', FALSE)) {
	/**
	 *
	 *
	 * @package assetic
	 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License, version 3 or later
	 *
	 */
	// class AssetController extends \TYPO3\CMS\Extbase\Mvc\Controller\ActionController {
	class AssetController extends \Tx_Extbase_MVC_Controller_ActionController {
		/**
		 * Compiler instance
		 * @var Cundd\Assetic\Plugin
		 */
		protected $compiler;

		/**
		 * The property mapper
		 *
		 * @var Tx_Extbase_Property_PropertyMapper
		 * @inject
		 */
		protected $propertyMapper;

		/**
		 * The property mapping configuration builder
		 *
		 * @var Tx_Extbase_Property_PropertyMappingConfigurationBuilder
		 * @inject
		 */
		protected $propertyMappingConfigurationBuilder;

		/**
		 * action list
		 *
		 * @return void
		 */
		public function listAction() {
			$assetCollection = array();
			$compiler = $this->getCompiler();

			$this->pd($compiler);

			if ($compiler) {
				$compiler->collectAssets();
				if ($compiler->getAssetManager()->has('cundd_assetic')) {
					$this->pd($compiler->getAssetManager()->get('cundd_assetic'));
					$assetCollection = $compiler->getAssetManager()->get('cundd_assetic');
				}
				if (!empty($assetCollection)) {
					$this->pd($assetCollection);
					$this->view->assign('assets', $assetCollection);
				} else {
					$this->flashMessageContainer->add('No assets found');
				}
			}
		}

		/**
		 * action show
		 *
		 * @return void
		 */
		public function compileAction() {
			$compiler = $this->getCompiler();
			if ($compiler) {
				$compiler->collectAssets();

				try {
					$compiler->compile();

					$outputFileLink = $compiler->getOutputFilePath();
					if (defined('TYPO3_MODE') && TYPO3_MODE === 'BE') {
						$outputFileLink = '../' . $outputFileLink;
					}
					$outputFileLink = '<a href="' . $outputFileLink . '">' . $compiler->getOutputFilePath() . '</a>';
					$this->flashMessageContainer->add('Stylesheets have been compiled to ' . $outputFileLink, '', \t3lib_Flashmessage::OK);
				} catch (\Exception $exception) {
					$this->flashMessageContainer->add('Could not compile files: #' . $exception->getCode() . ': ' . $exception->getMessage());
				}
			}
			$this->pd($compiler);
			$this->redirect('list');
		}

		/**
		 * Returns a compiler instance with the configuration
		 * @return Cundd\Assetic\Plugin
		 */
		public function getCompiler() {
			if (!$this->compiler) {
				$configuration = array();

				#$allConfiguration = $this->configurationManager->getConfiguration(\TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface::CONFIGURATION_TYPE_FULL_TYPOSCRIPT);
				$allConfiguration = $this->configurationManager->getConfiguration(\Tx_Extbase_Configuration_ConfigurationManagerInterface::CONFIGURATION_TYPE_FULL_TYPOSCRIPT);
				if (isset($allConfiguration['plugin.']) && isset($allConfiguration['plugin.']['CunddAssetic.'])) {
					$configuration = $allConfiguration['plugin.']['CunddAssetic.'];
					$this->compiler = new Plugin();
					$this->compiler->setConfiguration($configuration);
				} else {
					$this->flashMessageContainer->add('No configuration found');
				}
			}
			return $this->compiler;
		}

		/**
		 * Dumps a given variable (or the given variables) wrapped into a 'pre' tag.
		 *
		 * @param	mixed	$var1
		 * @return	string The printed content
		 */
		public function pd($var1 = '__iresults_pd_noValue') {
			if (class_exists('Tx_Iresults')) {
				$arguments = func_get_args();
				call_user_func_array(array('Tx_Iresults', 'pd'), $arguments);
			}
		}
	}
}
class_alias('Cundd\\Assetic\\Controller\\AssetController', 'Tx_Assetic_Controller_AssetController', FALSE);
?>