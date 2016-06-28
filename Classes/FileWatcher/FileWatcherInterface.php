<?php
/*
 *  Copyright notice
 *
 *  (c) 2016 Andreas Thurnheer-Meier <tma@iresults.li>, iresults
 *  Daniel Corn <cod@iresults.li>, iresults
 *
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 */

/**
 * @author COD
 * Created 17.05.16 10:34
 */
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
     * @param int $interval
     * @return $this
     */
    public function setInterval($interval);
}
