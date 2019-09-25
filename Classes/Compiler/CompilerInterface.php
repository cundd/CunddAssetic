<?php
declare(strict_types=1);

namespace Cundd\Assetic\Compiler;

use Assetic\Asset\AssetCollection;
use Assetic\Factory\AssetFactory;

/**
 * Interface for the compiler
 */
interface CompilerInterface
{
    /**
     * Collects all the assets and adds them to the asset manager
     *
     * @return \Assetic\Asset\AssetCollection
     * @throws \LogicException if the assetic classes could not be found
     */
    public function collectAssets();

    /**
     * Collects the files and tells assetic to compile the files
     *
     * @return bool Returns if the files have been compiled successfully
     * @throws \Exception if an exception is thrown during rendering
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
