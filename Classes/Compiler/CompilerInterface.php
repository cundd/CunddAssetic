<?php


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
