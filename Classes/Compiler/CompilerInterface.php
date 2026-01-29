<?php

declare(strict_types=1);

namespace Cundd\Assetic\Compiler;

use Cundd\Assetic\Configuration;
use Cundd\Assetic\ValueObject\PathWithoutHash;
use Cundd\Assetic\ValueObject\Result;
use Throwable;

/**
 * Interface for the compiler
 */
interface CompilerInterface
{
    /**
     * Collect the files and tell assetic to compile the files
     *
     * Return `Ok` if the files have been compiled successfully, otherwise an
     * `Err<Exception>` containing the exception
     *
     * @return Result<null,Throwable>
     */
    public function compile(
        Configuration $configuration,
        PathWithoutHash $outputPath,
    ): Result;
}
