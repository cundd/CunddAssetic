<?php
declare(strict_types=1);

namespace Cundd\Assetic\FileWatcher;

use Cundd\Assetic\Exception\FilePathException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RecursiveRegexIterator;
use RegexIterator;

/**
 * Class to test files for changes
 */
class FileWatcher implements FileWatcherInterface
{
    /**
     * Array of watched files
     *
     * @var string[]
     */
    private $watchedFilesCache = [];

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
    private $watchPaths = [];

    /**
     * Timestamp of the last re-compile
     *
     * @var integer
     */
    private $lastChangeTime;

    /**
     * @var string[]
     */
    private $assetSuffixes;

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
    public function getFindFilesMaxDepth(): int
    {
        return $this->findFilesMaxDepth;
    }

    /**
     * Sets the maximum directory depth of file to watch
     *
     * @param int $findFilesMaxDepth
     */
    public function setFindFilesMaxDepth(int $findFilesMaxDepth): void
    {
        $this->findFilesMaxDepth = $findFilesMaxDepth;
    }

    /**
     * Returns the array of file suffix to watch for changes
     *
     * @return string[]
     */
    public function getAssetSuffixes(): array
    {
        return $this->assetSuffixes;
    }

    /**
     * Sets the array of file suffix to watch for changes
     *
     * @param string[] $assetSuffixes
     * @return $this
     */
    public function setAssetSuffixes(array $assetSuffixes): FileWatcherInterface
    {
        $this->assetSuffixes = $assetSuffixes;

        return $this;
    }

    /**
     * @return string[]
     */
    public function getWatchPaths(): array
    {
        return $this->watchPaths;
    }

    /**
     * @param string[] $watchPaths
     * @return $this
     */
    public function setWatchPaths(array $watchPaths): FileWatcherInterface
    {
        if ($watchPaths && 0 === count(array_filter($watchPaths, 'file_exists'))) {
            $errorMessage = count($watchPaths) > 1
                ? sprintf('None of the watch paths "%s" exist', implode('", "', $watchPaths))
                : sprintf('Watch path "%s" does not exist', end($watchPaths));
            throw new FilePathException($errorMessage, 1555493966);
        }
        $this->watchPaths = $watchPaths;

        return $this;
    }

    /**
     * If a file changed it's path will be returned, otherwise NULL
     *
     * @return string|null
     */
    public function getChangedFileSinceLastCheck(): ?string
    {
        $lastCompileTime = $this->lastChangeTime;
        $foundFiles = $this->collectFilesToWatch();

        foreach ($foundFiles as $currentFile) {
            if (file_exists($currentFile) && filemtime($currentFile) > $lastCompileTime) {
                $this->lastChangeTime = time();

                return $currentFile;
            }
        }

        return null;
    }

    /**
     * Returns the files that are watched
     *
     * @return string[]
     */
    public function collectFilesToWatch(): array
    {
        $currentTime = time();
        if (($currentTime - $this->watchedFilesCacheTime) > $this->watchedFilesCacheLifetime) {
            $foundFiles = [];

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
     * @param float $interval
     * @return $this
     */
    public function setInterval(float $interval): FileWatcherInterface
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
    private function findFilesBySuffix($suffix, string $startDirectory): array
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
