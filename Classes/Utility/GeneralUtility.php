<?php

declare(strict_types=1);

namespace Cundd\Assetic\Utility;

use RuntimeException;

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
final class GeneralUtility
{
    /**
     * Defines if debugging is enabled
     */
    protected static ?bool $willDebug = null;

    /**
     * Return if a backend user is logged in
     *
     * @deprecated use \Cundd\Assetic\Utility\BackendUserUtility::isUserLoggedIn()
     */
    public static function isBackendUser(): bool
    {
        return BackendUserUtility::isUserLoggedIn();
    }

    /**
     * Dump a given variable (or the given variables) wrapped into a 'pre' tag.
     */
    public static function pd(mixed $var1 = '__iresults_pd_noValue'): void
    {
        if (!self::willDebug()) {
            return;
        }

        $arguments = func_get_args();
        if (class_exists(Tx_Iresults::class)) {
            Tx_Iresults::pd(...$arguments);
        } elseif ('cli' !== php_sapi_name()) {
            echo '<pre>';
            foreach ($arguments as $argument) {
                var_dump($argument);
            }
            echo '</pre>';
        }
    }

    /**
     * Print the given message if debugging is enabled
     */
    public static function say(string $message): void
    {
        if (!self::willDebug()) {
            return;
        }
        if ('cli' === php_sapi_name()) {
            fwrite(STDOUT, $message . PHP_EOL);
        } else {
            echo "<pre>$message</pre>";
        }
    }

    /**
     * Print a profiling message
     */
    public static function profile(string $msg = ''): void
    {
        if (getenv('CUNDD_ASSETIC_DEBUG')) {
            static $lastCall = -1;
            static $profilerStart;
            if (-1 === $lastCall) {
                $lastCall = microtime(true);
                $profilerStart = microtime(true);
            }
            $currentTime = microtime(true);
            $requestTime = $_SERVER['REQUEST_TIME_FLOAT'] ?? 0;
            $outputStream = defined('STDOUT')
                ? STDOUT
                : fopen('php://output', 'a');

            if (false === $outputStream) {
                throw new RuntimeException('Could not open STDOUT for writing');
            }

            fwrite(
                $outputStream,
                sprintf(
                    '[%s] %.4f %.4f %.4f %s' . PHP_EOL,
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
     */
    private static function willDebug(): bool
    {
        if (null === self::$willDebug) {
            $key = 'cundd_assetic_debug';
            self::$willDebug = self::getRequestParameter($key)
                && BackendUserUtility::isUserLoggedIn();
        }

        return self::$willDebug;
    }

    private static function getRequestParameter(string $key): mixed
    {
        return $_GET[$key] ?? $_POST[$key] ?? null;
    }
}
