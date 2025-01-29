<?php

declare(strict_types=1);

namespace Cundd\Assetic;

use Assetic\Asset\AssetCollection;
use Cundd\Assetic\Compiler\CompilerInterface;
use Cundd\Assetic\ValueObject\FilePath;
use Cundd\Assetic\ValueObject\Result;
use LogicException;
use Throwable;

/**
 * Assetic Manager interface
 */
interface ManagerInterface
{
    /**
     * Collect all the assets and adds them to the asset manager
     *
     * @throws LogicException if the assetic classes could not be found
     */
    public function collectAssets(): AssetCollection;

    /**
     * Collect and compile Assets and return a Result with the path to the compiled stylesheet
     *
     * @return Result<FilePath,Throwable>
     */
    public function collectAndCompile(): Result;

    /**
     * Force asset re-compilation
     */
    public function forceCompile(): self;

    /**
     * Return if the files should be compiled
     */
    public function willCompile(): bool;

    /**
     * Return the symlink URI
     */
    public function getSymlinkUri(): string;

    /**
     * Return the Compiler instance
     */
    public function getCompiler(): CompilerInterface;

    /**
     * Remove the cached hash
     *
     * @deprecated
     */
    public function clearHashCache(): void;
}
