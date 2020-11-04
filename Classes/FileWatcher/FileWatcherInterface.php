<?php
declare(strict_types=1);

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
    public function getAssetSuffixes(): array;

    /**
     * Sets the array of file suffix to watch for changes
     *
     * @param string[] $assetSuffix
     * @return $this
     */
    public function setAssetSuffixes(array $assetSuffix): FileWatcherInterface;

    /**
     * @return string[]
     */
    public function getWatchPaths(): array;

    /**
     * @param string[] $watchPaths
     * @return $this
     */
    public function setWatchPaths(array $watchPaths): FileWatcherInterface;

    /**
     * If a file changed it's path will be returned, otherwise NULL
     *
     * @return string|null
     */
    public function getChangedFileSinceLastCheck(): ?string;

    /**
     * Returns the files that are watched
     *
     * @return string[]
     */
    public function collectFilesToWatch(): array;

    /**
     * @param float $interval
     * @return $this
     */
    public function setInterval(float $interval): FileWatcherInterface;
}
