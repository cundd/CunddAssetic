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

final class ProfilingUtility
{
    /**
     * Print a profiling message
     */
    public static function profile(string $msg = ''): void
    {
        if (!getenv('CUNDD_ASSETIC_DEBUG')) {
            return;
        }

        static $didInitialize = false;
        /** @var float|null $lastCall */
        static $lastCall = null;
        /** @var float|null $profilerStart */
        static $profilerStart = null;
        /** @var float|null $requestStartTime */
        static $requestStartTime = null;
        if (false === $didInitialize) {
            $lastCall = hrtime(true);
            $profilerStart = hrtime(true);
            $requestStartTime = isset($_SERVER['REQUEST_TIME_FLOAT']) && is_numeric($_SERVER['REQUEST_TIME_FLOAT'])
                ? (float) $_SERVER['REQUEST_TIME_FLOAT']
                : 0.0;
        }

        $currentHrtime = hrtime(true);
        $outputStream = defined('STDOUT')
            ? STDOUT
            : fopen('php://output', 'a');

        if (false === $outputStream) {
            throw new RuntimeException('Could not open STDOUT for writing');
        }

        fwrite(
            $outputStream,
            sprintf(
                '[%s] %8.4f %8.4fµs %8.4fµs %s' . PHP_EOL,
                date('Y-m-d H:i:s'),
                microtime(true) - $requestStartTime,
                ($currentHrtime - $profilerStart) / 1000,
                ($currentHrtime - $lastCall) / 1000,
                $msg
            )
        );
        $lastCall = $currentHrtime;
    }
}
