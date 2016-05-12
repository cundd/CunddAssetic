<?php
/**
 * Created by PhpStorm.
 * User: daniel
 * Date: 24/02/16
 * Time: 21:46
 */

namespace Cundd\Assetic\FileWatcher;

use Cundd\Assetic\Exception\FilePathException;


/**
 * Class to test files for changes
 *
 * @package Cundd\Assetic\FileWatcher
 */
class FileWatcher
{
    /**
     * Array of watched files
     *
     * @var string[]
     */
    private $watchedFilesCache = array();

    /**
     * Timestamp of the last directory scan
     *
     * @var int
     */
    private $watchedFilesCacheTime = 0;

    /**
     * Lifetime of the directory scan cache
     *
     * @var int
     */
    private $watchedFilesCacheLifetime = 5;

    /**
     * Max depth to collect files for
     *
     * @var int
     */
    private $findFilesMaxDepth = 7;

    /**
     * Array of paths to watch for changes
     *
     * @var string[]
     */
    private $watchPaths = array();

    /**
     * Timestamp of the last re-compile
     *
     * @var integer
     */
    private $lastChangeTime;

    /**
     * @return \string[]
     */
    public function getWatchPaths()
    {
        return $this->watchPaths;
    }

    /**
     * @param \string[] $watchPaths
     * @return $this
     */
    public function setWatchPaths(array $watchPaths)
    {
        if ($watchPaths && 0 === count(array_filter($watchPaths, 'file_exists'))) {
            throw new FilePathException(sprintf('None of the watch paths %s exist', implode(',', $watchPaths)));
        }
        $this->watchPaths = $watchPaths;

        return $this;
    }

    /**
     * If a file changed it's path will be returned, otherwise FALSE
     *
     * @return string|bool
     */
    public function getChangedFileSinceLastCheck()
    {
        $lastCompileTime = $this->lastChangeTime;
        $foundFiles = $this->collectFilesToWatch();

        foreach ($foundFiles as $currentFile) {
            if (filemtime($currentFile) > $lastCompileTime) {
                $this->lastChangeTime = time();

                return $currentFile;
            }
        }

        return false;
    }

    /**
     * Returns the files that are watched
     *
     * string[]
     */
    public function collectFilesToWatch()
    {
        $currentTime = time();
        if (($currentTime - $this->watchedFilesCacheTime) > $this->watchedFilesCacheLifetime) {
            $assetSuffix = array_merge(
                FileCategories::$scriptAssetSuffixes,
                FileCategories::$styleAssetSuffixes,
                FileCategories::$otherAssetSuffixes
            );
            $foundFiles = array();

            foreach ($this->watchPaths as $currentWatchPath) {
                $foundFilesForCurrentPath = $this->findFilesBySuffix($assetSuffix, $currentWatchPath);
                if ($foundFilesForCurrentPath) {
                    $foundFiles = array_merge($foundFiles, $foundFilesForCurrentPath);
                }
            }

            $this->watchedFilesCacheTime = $currentTime;
            $this->watchedFilesCache = $foundFiles;
        }

        return $this->watchedFilesCache;
    }

    /**
     * Returns all files with the given suffix under the given start directory
     *
     * @param string|string[] $suffix
     * @param string          $startDirectory
     * @return string[]
     */
    private function findFilesBySuffixWithoutGlobBrace($suffix, $startDirectory)
    {
        $foundFiles = array();
        if (is_array($suffix)) {
            foreach ($suffix as $currentSuffix) {
                $foundFiles = array_merge(
                    $foundFiles,
                    $this->findFilesBySuffixWithoutGlobBrace($currentSuffix, $startDirectory)
                );
            }

            return $foundFiles;
        } elseif (!is_string($suffix)) {
            throw new \InvalidArgumentException(
                sprintf('Expected argument suffix to be of type string, %s given', gettype($suffix)),
                1453993530
            );
        }

        $maxDepth = $this->findFilesMaxDepth;
        $startDirectory = rtrim($startDirectory, '/').'/';

        $pathPattern = $startDirectory.'*.'.$suffix;
        $foundFiles = glob($pathPattern);

        $i = 1;
        while ($i < $maxDepth) {
            $pattern = $startDirectory.str_repeat('*/*', $i).$suffix;
            $foundFiles = array_merge($foundFiles, glob($pattern));
            $i++;
        }

        return $foundFiles;
    }

    /**
     * Returns all files with the given suffix under the given start directory
     *
     * @param string|string[] $suffix
     * @param string          $startDirectory
     * @return string[]
     */
    private function findFilesBySuffixWithGlobBrace($suffix, $startDirectory)
    {
        $maxDepth = $this->findFilesMaxDepth;
        $suffixPattern = '.{'.implode(',', (array)$suffix).'}';
        $startDirectory = rtrim($startDirectory, '/').'/*';

        $foundFiles = glob($startDirectory.$suffixPattern, GLOB_BRACE);

        $i = 1;
        while ($i < $maxDepth) {
            $pattern = $startDirectory.str_repeat('*/*', $i).$suffixPattern;
            $foundFiles = array_merge($foundFiles, glob($pattern, GLOB_BRACE));
            $i++;
        }

        return $foundFiles;
    }

    /**
     * Returns all files with the given suffix under the given start directory
     *
     * @param string|string[] $suffix
     * @param string          $startDirectory
     * @return string[]
     */
    private function findFilesBySuffix($suffix, $startDirectory)
    {
        if (!defined('GLOB_BRACE')) {
            return $this->findFilesBySuffixWithoutGlobBrace($suffix, $startDirectory);
        }

        return $this->findFilesBySuffixWithGlobBrace($suffix, $startDirectory);
    }

    public function setInterval($interval)
    {
    }
}