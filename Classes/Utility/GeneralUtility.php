<?php
declare(strict_types=1);

namespace Cundd\Assetic\Utility;

use function fwrite;
use function getenv;
use function microtime;

/**
 * General utility class for debugging and message printing
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
     * @param mixed $var1
     */
    public static function pd($var1 = '__iresults_pd_noValue')
    {
        if (!self::willDebug()) {
            return;
        }

        $arguments = func_get_args();
        if (class_exists('Tx_Iresults')) {
            call_user_func_array(['Tx_Iresults', 'pd'], $arguments);
        } elseif (php_sapi_name() !== 'cli') {
            echo '<pre>';
            foreach ($arguments as $argument) {
                var_dump($argument);
            }
            echo '</pre>';
        }
    }

    /**
     * Prints the given message if debugging is enabled
     *
     * @param string $message
     */
    public static function say(string $message)
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
     * @param string $msg
     */
    public static function profile(string $msg = '')
    {
        if (getenv('CUNDD_ASSETIC_DEBUG')) {
            static $lastCall = -1;
            if ($lastCall === -1) {
                $lastCall = microtime(true);
            }
            $currentTime = microtime(true);
            $requestTime = isset($_SERVER['REQUEST_TIME_FLOAT']) ? $_SERVER['REQUEST_TIME_FLOAT'] : 0;
            fwrite(
                STDOUT,
                sprintf(
                    "[%s] %.4f %.4f %s" . PHP_EOL,
                    date('Y-m-d H:i:s'),
                    $currentTime - $requestTime,
                    $currentTime - $lastCall,
                    $msg
                )
            );
            $lastCall = microtime(true);
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
            $key = 'cundd_assetic_debug';
            if ((bool)self::getRequestParameter($key)) {
                self::$willDebug = true;
            }

            if (!self::isBackendUser()) {
                self::$willDebug = false;
            }
        }

        return self::$willDebug;
    }

    /**
     * @param string $key
     * @return mixed
     */
    protected static function getRequestParameter(string $key)
    {
        return $_GET[$key] ?? $_POST[$key] ?? null;
    }
}
