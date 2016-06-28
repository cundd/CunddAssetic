<?php
/**
 * Created by PhpStorm.
 * User: daniel
 * Date: 24/02/16
 * Time: 21:46
 */

namespace Cundd\Assetic\FileWatcher;

use Cundd\Assetic\Exception\FilePathException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RecursiveRegexIterator;
use RegexIterator;

/**
 * Class to test files for changes
 *
 * @package Cundd\Assetic\FileWatcher
 */
class FileWatcher implements FileWatcherInterface
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
     * @var string[]
     */
    private $assetSuffixes = [];

    /**
     * FileWatcher constructor
     */
    public function __construct()
    {
        $this->assetSuffixes = array_merge(
            FileCategories::$scriptAssetSuffixes,
            FileCategories::$styleAssetSuffixes,
            FileCategories::$otherAssetSuffixes
        );
    }

    /**
     * Returns the maximum directory depth of file to watch
     *
     * @return int
     */
    public function getFindFilesMaxDepth()
    {
        return $this->findFilesMaxDepth;
    }

    /**
     * Sets the maximum directory depth of file to watch
     *
     * @param int $findFilesMaxDepth
     */
    public function setFindFilesMaxDepth($findFilesMaxDepth)
    {
        $this->findFilesMaxDepth = $findFilesMaxDepth;
    }

    /**
     * Returns the array of file suffix to watch for changes
     *
     * @return string[]
     */
    public function getAssetSuffixes()
    {
        return $this->assetSuffixes;
    }

    /**
     * Sets the array of file suffix to watch for changes
     *
     * @param string[] $assetSuffixes
     * @return $this
     */
    public function setAssetSuffixes(array $assetSuffixes)
    {
        $this->assetSuffixes = $assetSuffixes;

        return $this;
    }

    /**
     * @return string[]
     */
    public function getWatchPaths()
    {
        return $this->watchPaths;
    }

    /**
     * @param string[] $watchPaths
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
     * @return string[]
     */
    public function collectFilesToWatch()
    {
        $currentTime = time();
        if (($currentTime - $this->watchedFilesCacheTime) > $this->watchedFilesCacheLifetime) {
            $foundFiles = array();

            foreach ($this->watchPaths as $currentWatchPath) {
                $foundFilesForCurrentPath = $this->findFilesBySuffix($this->assetSuffixes, $currentWatchPath);
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
     * @param int $interval
     * @return $this
     */
    public function setInterval($interval)
    {
        return $this;
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
        $directoryIterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($startDirectory));
        $regexIterator = new RegexIterator(
            $directoryIterator,
            sprintf('/^.+\.(%s)$/i', implode('|', $suffix)),
            RecursiveRegexIterator::GET_MATCH
        );

        return array_filter(
            array_map(
                function ($pathCollection) {
                    return $pathCollection[0];
                },
                iterator_to_array($regexIterator)
            ),
            'is_file'
        );
    }
}
