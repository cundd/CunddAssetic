<?php

declare(strict_types=1);

namespace Cundd\Assetic\Compiler;

use Assetic\Asset\AssetCollection;
use Assetic\Factory\AssetFactory;
use Cundd\Assetic\ValueObject\Result;
use LogicException;

/**
 * Interface for the compiler
 */
interface CompilerInterface
{
    /**
     * Collect all the assets and add them to the asset manager
     *
     * @throws LogicException if the assetic classes could not be found
     */
    public function collectAssets(): AssetCollection;

    /**
     * Collect the files and tell assetic to compile the files
     *
     * @return Result Return `Ok<NULL>` if the files have been compiled successfully, otherwise an `Err<Exception>` containing the exception
     */
    public function compile(): Result;

    /**
     * Create and collect the Asset with the given key and stylesheet
     */
    public function createAsset(
        string $assetKey,
        string $stylesheet,
        AssetCollection $assetCollection,
        AssetFactory $factory,
    ): ?AssetCollection;
}
