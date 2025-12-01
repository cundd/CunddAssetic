<?php

declare(strict_types=1);

namespace Cundd\Assetic\Compiler;

use Assetic\Asset\AssetCollection;
use Cundd\Assetic\Configuration;
use Cundd\Assetic\ValueObject\Result;
use LogicException;
use Throwable;

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
    public function collectAssets(Configuration $configuration): AssetCollection;

    /**
     * Collect the files and tell assetic to compile the files
     *
     * Return `Ok` if the files have been compiled successfully, otherwise an
     * `Err<Exception>` containing the exception
     *
     * @return Result<null,Throwable>
     */
    public function compile(Configuration $configuration): Result;
}
