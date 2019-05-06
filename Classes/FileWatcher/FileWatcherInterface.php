<?php

namespace Cundd\Assetic\FileWatcher;

/**
 * Interface for classes that test files for changes
 */
interface FileWatcherInterface
{
    /**
     * Returns the array of file suffix to watch for changes
     *
     * @return string[]
     */
    public function getAssetSuffixes();

    /**
     * Sets the array of file suffix to watch for changes
     *
     * @param string[] $assetSuffix
     * @return $this
     */
    public function setAssetSuffixes(array $assetSuffix);

    /**
     * @return string[]
     */
    public function getWatchPaths();

    /**
     * @param string[] $watchPaths
     * @return $this
     */
    public function setWatchPaths(array $watchPaths);

    /**
     * If a file changed it's path will be returned, otherwise FALSE
     *
     * @return string|bool
     */
    public function getChangedFileSinceLastCheck();

    /**
     * Returns the files that are watched
     *
     * @return string[]
     */
    public function collectFilesToWatch();

    /**
     * @param float $interval
     * @return $this
     */
    public function setInterval(float $interval);
}
