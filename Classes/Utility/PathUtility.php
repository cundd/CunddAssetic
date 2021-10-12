<?php
declare(strict_types=1);

namespace Cundd\Assetic\Utility;

use TYPO3\CMS\Core\Utility\GeneralUtility;
use function dirname;
use function realpath;
use function rtrim;
use function substr;

class PathUtility
{
    public static function getAbsolutePath(string $path): string
    {
        if ($path[0] === '~') {
            return ($_SERVER['HOME'] ?? '') . substr($path, 1);
        }

        if (substr($path, 0, 4) === 'EXT:') {
            return dirname(GeneralUtility::getFileAbsFileName(rtrim($path, '/') . '/fake-file'));
        }

        return realpath($path) ?: $path;
    }
}
