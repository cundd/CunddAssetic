<?php
declare(strict_types=1);

namespace Cundd\Assetic\Compiler;

use Assetic\Asset\AssetCollection;
use Assetic\AssetManager;
use Assetic\Factory\AssetFactory;
use Cundd\Assetic\ValueObject\Result;
use LogicException;

/**
 * Interface for the compiler
 */
interface CompilerInterface
{
    /**
     * Collect all the assets and adds them to the asset manager
     *
     * @return AssetCollection
     * @throws LogicException if the assetic classes could not be found
     */
    public function collectAssets(): AssetCollection;

    /**
     * Collect the files and tells assetic to compile the files
     *
     * @return Result Return `Ok<NULL>` if the files have been compiled successfully, otherwise an `Err<Exception>` containing the exception
     */
    public function compile(): Result;

    /**
     * Return the shared asset manager
     *
     * @return AssetManager
     */
    public function getAssetManager(): AssetManager;

    /**
     * Create and collect the Asset with the given key and stylesheet
     *
     * @param string          $assetKey
     * @param string          $stylesheet
     * @param AssetCollection $assetCollection
     * @param AssetFactory    $factory
     * @return AssetCollection|null
     */
    public function createAsset(
        string $assetKey,
        string $stylesheet,
        AssetCollection $assetCollection,
        AssetFactory $factory
    ): ?AssetCollection;
}
