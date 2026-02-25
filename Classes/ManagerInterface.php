<?php

declare(strict_types=1);

namespace Cundd\Assetic;

use Cundd\Assetic\ValueObject\CompilationContext;
use Cundd\Assetic\ValueObject\ManagerResultInfo;
use Cundd\Assetic\ValueObject\Result;
use Throwable;

/**
 * Assetic Manager interface
 */
interface ManagerInterface
{
    /**
     * Collect and compile Assets and return a Result with the path to the compiled stylesheet
     *
     * @return Result<ManagerResultInfo,Throwable>
     */
    public function collectAndCompile(
        Configuration $configuration,
        CompilationContext $compilationContext,
    ): Result;

    /**
     * Return if the files should be compiled
     */
    public function willCompile(
        Configuration $configuration,
        CompilationContext $compilationContext,
    ): bool;
}
