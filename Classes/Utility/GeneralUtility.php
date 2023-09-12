<?php

declare(strict_types=1);

namespace Cundd\Assetic\Utility;

use function date;
use function defined;
use function fopen;
use function fwrite;
use function getenv;
use function microtime;
use function sprintf;

use const PHP_EOL;
use const STDOUT;

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
     * Return if a backend user is logged in
     *
     * @return bool
     * @deprecated use \Cundd\Assetic\Utility\BackendUserUtility::isUserLoggedIn()
     */
    public static function isBackendUser(): bool
    {
        return BackendUserUtility::isUserLoggedIn();
    }

    /**
     * Dump a given variable (or the given variables) wrapped into a 'pre' tag.
     *
     * @param mixed $var1
     */
    public static function pd($var1 = '__iresults_pd_noValue'): void
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
     * Print the given message if debugging is enabled
     *
     * @param string $message
     */
    public static function say(string $message): void
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
     * Print a profiling message
     *
     * @param string $msg
     */
    public static function profile(string $msg = ''): void
    {
        if (getenv('CUNDD_ASSETIC_DEBUG')) {
            static $lastCall = -1;
            static $profilerStart;
            if ($lastCall === -1) {
                $lastCall = microtime(true);
                $profilerStart = microtime(true);
            }
            $currentTime = microtime(true);
            $requestTime = $_SERVER['REQUEST_TIME_FLOAT'] ?? 0;
            $outputStream = defined('STDOUT') ? STDOUT : fopen('php://output', 'a');
            fwrite(
                $outputStream,
                sprintf(
                    "[%s] %.4f %.4f %.4f %s" . PHP_EOL,
                    date('Y-m-d H:i:s'),
                    $currentTime - $requestTime,
                    $currentTime - $profilerStart,
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
    private static function willDebug(): bool
    {
        if (self::$willDebug === -1) {
            $key = 'cundd_assetic_debug';
            self::$willDebug = self::getRequestParameter($key) && BackendUserUtility::isUserLoggedIn();
        }

        return self::$willDebug;
    }

    /**
     * @param string $key
     * @return mixed
     */
    private static function getRequestParameter(string $key)
    {
        return $_GET[$key] ?? $_POST[$key] ?? null;
    }
}
