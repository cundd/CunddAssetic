<?php

namespace Cundd\Assetic;

/*
 * Copyright (C) 2012 Daniel Corn
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 */

use Cundd\Assetic\Compiler\CompilerInterface;

/**
 * Assetic Manager interface
 *
 * @package Cundd_Assetic
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
