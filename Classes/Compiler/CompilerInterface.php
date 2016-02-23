<?php
/*
 *  Copyright notice
 *
 *  (c) 2015 Andreas Thurnheer-Meier <tma@iresults.li>, iresults
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
 * Created 08.05.15 16:57
 */


namespace Cundd\Assetic\Compiler;

use Assetic\Asset\AssetCollection;
use Assetic\Factory\AssetFactory;


/**
 * Interface for the compiler
 *
 * @package Cundd\Assetic\Compiler
 */
interface CompilerInterface
{
    /**
     * Collects all the assets and adds them to the asset manager
     *
     * @throws \LogicException if the assetic classes could not be found
     * @return \Assetic\Asset\AssetCollection
     */
    public function collectAssets();

    /**
     * Collects the files and tells assetic to compile the files
     *
     * @throws \Exception if an exception is thrown during rendering
     * @return bool Returns if the files have been compiled successfully
     */
    public function compile();

    /**
     * Returns the shared asset manager
     *
     * @return \Assetic\AssetManager
     */
    public function getAssetManager();

    /**
     * Create and collect the Asset with the given key and stylesheet
     *
     * @param string          $assetKey
     * @param string          $stylesheet
     * @param AssetCollection $assetCollection
     * @param AssetFactory    $factory
     * @return AssetCollection|null
     */
    public function createAsset($assetKey, $stylesheet, AssetCollection $assetCollection, AssetFactory $factory);
}
