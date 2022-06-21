<?php
declare(strict_types=1);

namespace Cundd\Assetic;

use Assetic\Asset\AssetCollection;
use Cundd\Assetic\Compiler\CompilerInterface;
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
     * Collect and compiles assets and returns the relative path to the compiled stylesheet
     *
     * @return string
     */
    public function collectAndCompile(): string;

    /**
     * Force asset re-compilation
     *
     * @return void
     */
    public function forceCompile(): void;

    /**
     * Return if the files should be compiled
     *
     * @return boolean
     */
    public function willCompile(): bool;

    /**
     * Return the current output filename
     *
     * @return string
     */
    public function getOutputFilePath(): string;

    /**
     * Return the current output filename
     *
     * The current output filename may be changed if when the hash of the
     * filtered asset file is generated
     *
     * @return string
     */
    public function getCurrentOutputFilename(): string;

    /**
     * Return the symlink URI
     *
     * @return string
     */
    public function getSymlinkUri(): string;

    /**
     * Return the symlink path
     *
     * @return string
     */
    public function getSymlinkPath(): string;

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
     */
    public function clearHashCache(): void;

    /**
     * Return if experimental features are enabled
     *
     * @return boolean
     * @deprecated use \Cundd\Assetic\Configuration\ConfigurationProvider::getEnableExperimentalFeatures() instead
     */
    public function getExperimental(): bool;
}
