<?php

declare(strict_types=1);

namespace Cundd\Assetic\Utility;

use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Utility\GeneralUtility;

use function clearstatcache;
use function dirname;
use function file_exists;
use function is_link;
use function is_writable;
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
        if (substr($path, 0, 12) === 'ProjectPath:') {
            return Environment::getProjectPath() . substr($path, 12);
        }

        return realpath($path) ?: $path;
    }

    /**
     * Try to detect the reason for the write-failure
     *
     * @param string $path
     * @return string
     */
    public static function getReasonForWriteFailure(string $path): string
    {
        clearstatcache(true, $path);
        if (file_exists($path)) {
            $reason = 'the file exists';
        } elseif (is_link($path)) {
            $reason = 'it is a link';
        } elseif (!is_writable(dirname($path))) {
            $reason = 'the directory is not writable';
        } elseif (!is_writable($path)) {
            $reason = 'the file path is not writable';
        } else {
            $reason = 'of an unknown reason';
        }

        return $reason;
    }
}
