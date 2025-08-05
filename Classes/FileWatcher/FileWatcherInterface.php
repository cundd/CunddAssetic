<?php

declare(strict_types=1);

namespace Cundd\Assetic\FileWatcher;

/**
 * Interface for classes that test files for changes
 */
interface FileWatcherInterface
{
    /**
     * Return the array of file suffix to watch for changes
     *
     * @return string[]
     */
    public function getAssetSuffixes(): array;

    /**
     * Set the array of file suffix to watch for changes
     *
     * @param string[] $assetSuffixes
     *
     * @return $this
     */
    public function setAssetSuffixes(array $assetSuffixes): FileWatcherInterface;

    /**
     * @return string[]
     */
    public function getWatchPaths(): array;

    /**
     * @param string[] $watchPaths
     *
     * @return $this
     */
    public function setWatchPaths(array $watchPaths): FileWatcherInterface;

    /**
     * If a file changed it's path will be returned, otherwise NULL
     */
    public function getChangedFileSinceLastCheck(): ?string;

    /**
     * Return the files that are watched
     *
     * @return string[]
     */
    public function collectFilesToWatch(): array;

    /**
     * @return $this
     */
    public function setInterval(float $interval): FileWatcherInterface;
}
