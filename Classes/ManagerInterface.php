<?php

namespace Cundd\Assetic;


use Cundd\Assetic\Compiler\CompilerInterface;

/**
 * Assetic Manager interface
 */
interface ManagerInterface
{
    /**
     * Collects all the assets and adds them to the asset manager
     *
     * @throws \LogicException if the assetic classes could not be found
     * @return \Assetic\Asset\AssetCollection
     */
    public function collectAssets();

    /**
     * Collects and compiles assets and returns the relative path to the compiled stylesheet
     *
     * @return string
     */
    public function collectAndCompile();

    /**
     * Force asset re-compilation
     *
     * @return void
     */
    public function forceCompile();

    /**
     * Returns if the files should be compiled
     *
     * @return boolean
     */
    public function willCompile();

    /**
     * Returns the current output filename
     *
     * @return string
     */
    public function getOutputFilePath();

    /**
     * Returns the current output filename
     *
     * The current output filename may be changed if when the hash of the
     * filtered asset file is generated
     *
     * @return string
     */
    public function getCurrentOutputFilename();

    /**
     * Returns the symlink URI
     *
     * @return string
     */
    public function getSymlinkUri();

    /**
     * Returns the symlink path
     *
     * @return string
     */
    public function getSymlinkPath();

    /**
     * Returns the Compiler instance
     *
     * @return CompilerInterface
     */
    public function getCompiler();

    /**
     * Remove the cached hash
     *
     * @return void
     */
    public function clearHashCache();

    /**
     * Returns if experimental features are enabled
     *
     * @return boolean
     */
    public function getExperimental();
}
