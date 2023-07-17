<?php

declare(strict_types=1);

namespace Cundd\Assetic;

use Assetic\Asset\AssetCollection;
use Cundd\Assetic\Compiler\CompilerInterface;
use Cundd\Assetic\ValueObject\FilePath;
use Cundd\Assetic\ValueObject\Result;
use LogicException;

/**
 * Assetic Manager interface
 */
interface ManagerInterface
{
    /**
     * Collect all the assets and adds them to the asset manager
     *
     * @return AssetCollection
     * @throws LogicException if the assetic classes could not be found
     */
    public function collectAssets(): AssetCollection;

    /**
     * Collect and compile Assets and return a Result with the path to the compiled stylesheet
     *
     * @return Result<FilePath>
     */
    public function collectAndCompile(): Result;

    /**
     * Force asset re-compilation
     *
     * @return self
     */
    public function forceCompile(): self;

    /**
     * Return if the files should be compiled
     *
     * @return boolean
     */
    public function willCompile(): bool;

    /**
     * Return the symlink URI
     *
     * @return string
     */
    public function getSymlinkUri(): string;

    /**
     * Return the Compiler instance
     *
     * @return CompilerInterface
     */
    public function getCompiler(): CompilerInterface;

    /**
     * Remove the cached hash
     *
     * @return void
     * @deprecated
     */
    public function clearHashCache(): void;
}
