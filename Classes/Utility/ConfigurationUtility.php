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

use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility as TYPO3GeneralUtility;

/**
 * Helper class to read configuration
 *
 * @package Cundd\Assetic\Utility
 */
class ConfigurationUtility
{
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
    protected static $domainContext;

    /**
     * Path to the output file directory
     *
     * @var string
     */
    protected static $outputFileDir = 'typo3temp/cundd_assetic/';

    /**
     * Returns the extension configuration for the given key
     *
     * @param string $key
     * @return mixed
     */
    public static function getExtensionConfiguration($key)
    {
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

        return null;
    }

    /**
     * Returns if the current installation is a multidomain installation
     *
     * @return boolean
     */
    public static function isMultiDomain()
    {
        return !!intval(self::getExtensionConfiguration('multidomain'));
    }

    /**
     * Sets the domain for the current context
     *
     * @param string|int $domainContext Domain as string or the page UID to read the domain from
     * @throws \UnexpectedValueException if the Backend Utility class was not found or can not be used
     */
    public static function setDomainContext($domainContext)
    {
        if (is_numeric($domainContext)) {
            if (!class_exists('TYPO3\\CMS\\Backend\\Utility\\BackendUtility')) {
                throw new \UnexpectedValueException('Backend Utility class not found', 1408363869);
            }
            $domainContext = BackendUtility::getViewDomain($domainContext);
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
    public static function getDomainContext()
    {
        if (!self::$domainContext) {
            $domainContextTemp = '';
            if (TYPO3_MODE == 'BE') {
                $domainContextTemp = TYPO3GeneralUtility::_GP('id');
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
     * Returns the relevant domain to be attached to the cache identifier to distinguish the websites in a multi-domain
     * installation
     *
     * @return string
     */
    public static function getDomainIdentifier()
    {
        if (!ConfigurationUtility::isMultiDomain()) {
            return '';
        }

        $domain = ConfigurationUtility::getDomainContext();
        if (substr($domain, 0, 7) === 'http://') {
            $domain = substr($domain, 7);
        } elseif (substr($domain, 0, 8) === 'https://') {
            $domain = substr($domain, 8);
        }

        $domain = str_replace('.', '', $domain);

        return $domain . '-';
    }

    /**
     * Returns if the given host is valid
     *
     * @param string $host
     * @return boolean
     */
    protected static function _validateHost($host)
    {
        // Remove any dash ('-'), dot ('.') and colon (':', allowed because of the port)
        return ctype_alnum(str_replace(['-', '.', ':'], '', $host));
    }

    /**
     * Returns the path to the web directory
     *
     * @return string
     */
    public static function getPathToWeb()
    {
        return defined('PATH_site') ? PATH_site : '';
    }

    /**
     * Returns the path to the output file directory
     *
     * @return string $outputFileDir
     */
    public static function getOutputFileDir()
    {
        return self::$outputFileDir;
    }

    /**
     * Sets the path to the output file directory
     *
     * @param string $outputFileDir
     */
    public static function setOutputFileDir($outputFileDir)
    {
        self::$outputFileDir = $outputFileDir;
    }

    /**
     * Returns if development mode is on
     *
     * @param array $configuration Configuration to check
     * @return bool
     */
    public static function isDevelopment(array $configuration)
    {
        if (php_sapi_name() === 'cli') {
            return true;
        }

        return isset($configuration['development']) ? (bool)intval($configuration['development']) : false;
    }
}
