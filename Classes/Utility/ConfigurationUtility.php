<?php
/*
 *  Copyright notice
 *
 *  (c) 2014 Andreas Thurnheer-Meier <tma@iresults.li>, iresults
 *  Daniel Corn <cod@iresults.li>, iresults
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

/**
 * @author COD
 * Created 18.08.14 14:13
 */


namespace Cundd\Assetic\Utility;

/**
 * Helper class to read configuration
 *
 * @package Cundd\Assetic\Utility
 */
class ConfigurationUtility {
	/**
	 * Extension key to use
	 */
	const EXTENSION_KEY = 'assetic';

	/**
	 * Domain in the current context
	 *
	 * This may be read through the Backend Utility using the GET parameter "id", the SERVER_NAME or HTTP_HOST server
	 * variables or may be set using setDomainContext()
	 *
	 * @var string
	 */
	static protected $domainContext;

	/**
	 * Returns the extension configuration for the given key
	 *
	 * @param string $key
	 * @return mixed
	 */
	static public function getExtensionConfiguration($key) {
		// Read the configuration from the globals
		static $configuration;
		if (!$configuration) {
			if (isset($GLOBALS['TYPO3_CONF_VARS'])
				&& isset($GLOBALS['TYPO3_CONF_VARS']['EXT'])
				&& isset($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf'])
				&& isset($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf'][self::EXTENSION_KEY])
			) {
				$configuration = unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf'][self::EXTENSION_KEY]);
			}
		}

		if (isset($configuration[$key])) {
			return $configuration[$key];
		}
		return NULL;
	}

	/**
	 * Returns if the current installation is a multidomain installation
	 *
	 * @return boolean
	 */
	static public function isMultiDomain() {
		return !!intval(self::getExtensionConfiguration('multidomain'));
	}

	/**
	 * Sets the domain for the current context
	 *
	 * @param string|int $domainContext Domain as string or the page UID to read the domain from
	 * @throws \UnexpectedValueException if the Backend Utility class was not found or can not be used
	 */
	static public function setDomainContext($domainContext) {
		if (is_numeric($domainContext)) {
			if (!class_exists('t3lib_befunc')) throw new \UnexpectedValueException('Backend Utility class not found', 1408363869);
			$domainContext = \t3lib_befunc::getViewDomain($domainContext);
		}
		self::$domainContext = $domainContext;
	}

	/**
	 * Returns the domain in the current context
	 *
	 * This may be read through the Backend Utility using the GET parameter "id", the SERVER_NAME or HTTP_HOST server
	 * variables or may be set using setDomainContext()
	 *
	 * @return string
	 */
	static public function getDomainContext() {
		if (!self::$domainContext) {
			$domainContextTemp = '';
			if (TYPO3_MODE == 'BE') {
				$domainContextTemp = \t3lib_div::_GP('id');
			}

			if (TYPO3_MODE != 'BE' || !$domainContextTemp) {
				if (isset($_SERVER['SERVER_NAME'])) {
					$domainContextTemp = $_SERVER['SERVER_NAME'];
					if (!self::_validateHost($domainContextTemp)) {
						$domainContextTemp = '';
					}
				}
				if (!$domainContextTemp && isset($_SERVER['HTTP_HOST'])) {
					$domainContextTemp = $_SERVER['HTTP_HOST'];
					if (!self::_validateHost($domainContextTemp)) {
						$domainContextTemp = '';
					}
				}
			}
			self::setDomainContext($domainContextTemp);
		}
		return self::$domainContext;
	}

	/**
	 * Returns if the given host is valid
	 *
	 * @param string $host
	 * @return boolean
	 */
	static protected function _validateHost($host) {
		// Remove any dash ('-'), dot ('.') and colon (':', allowed because of the port)
		return ctype_alnum(str_replace(array('-', '.', ':'), '', $host));
	}
}