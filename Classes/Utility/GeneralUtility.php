<?php
/*
 *  Copyright notice
 *
 *  (c) 2015 Andreas Thurnheer-Meier <tma@iresults.li>, iresults
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
 * Created 08.05.15 16:14
 */


namespace Cundd\Assetic\Utility;

/**
 * General utility class for debugging and message printing
 *
 * @package Cundd\Assetic\Utility
 */
abstract class GeneralUtility
{

    /**
     * Defines if debugging is enabled
     *
     * @var bool
     */
    protected static $willDebug = -1;

    /**
     * Returns if a backend user is logged in
     *
     * @return bool
     */
    public static function isBackendUser()
    {
        if (!isset($GLOBALS['BE_USER'])
            || !isset($GLOBALS['BE_USER']->user)
            || !intval($GLOBALS['BE_USER']->user['uid'])
        ) {
            return false;
        }
        return true;
    }

    /**
     * Dumps a given variable (or the given variables) wrapped into a 'pre' tag.
     *
     * @param    mixed $var1
     */
    public static function pd($var1 = '__iresults_pd_noValue')
    {
        if (!self::willDebug()) {
            return;
        }

        $arguments = func_get_args();
        if (class_exists('Tx_Iresults')) {
            call_user_func_array(array('Tx_Iresults', 'pd'), $arguments);
        } else {
            if (php_sapi_name() !== 'cli') {
                echo '<pre>';
                foreach ($arguments as $argument) {
                    var_dump($argument);
                }
                echo '</pre>';
            }
        }
    }

    /**
     * Prints the given message if debugging is enabled
     *
     * @param string $message
     */
    public static function say($message)
    {
        if (!self::willDebug()) {
            return;
        }
        if (php_sapi_name() === 'cli') {
            fwrite(STDOUT, $message . PHP_EOL);
        } else {
            echo "<pre>$message</pre>";
        }
    }

    /**
     * Print a profiling message.
     *
     * @param    string $msg
     * @return    string The printed content
     */
    public static function profile($msg = '')
    {
        if (class_exists('Tx_Iresults_Profiler')) {
            \Tx_Iresults_Profiler::profile($msg);
        }
    }

    /**
     * Returns if debugging is enabled
     *
     * @return bool
     */
    protected static function willDebug()
    {
        if (self::$willDebug === -1) {
            self::$willDebug = false;
            if (
                (isset($_GET['cundd_assetic_debug']) && $_GET['cundd_assetic_debug'])
                || (isset($_POST['cundd_assetic_debug']) && $_POST['cundd_assetic_debug'])
            ) {
                self::$willDebug = true;
            }

            if (!self::isBackendUser()) {
                self::$willDebug = false;
            }
        }
        return self::$willDebug;
    }
}
