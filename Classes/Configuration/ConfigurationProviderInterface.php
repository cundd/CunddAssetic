<?php

declare(strict_types=1);

namespace Cundd\Assetic\Configuration;

interface ConfigurationProviderInterface
{
    /**
     * Return the configurations for the stylesheets
     *
     * @return array
     */
    public function getStylesheetConfigurations(): array;

    /**
     * Return if re-compilation is enabled for guests
     *
     * If `FALSE` the assets will only be re-compiled if a backend user is logged in
     *
     * @return bool
     */
    public function getAllowCompileWithoutLogin(): bool;

    /**
     * Return the path to the web directory
     *
     * @return string
     */
    public function getPublicPath(): string;

    /**
     * Return the path to the output file directory relative to the web root
     *
     * @return string
     */
    public function getOutputFileDir(): string;

    /**
     * Return the absolute path to the output file directory
     *
     * @return string
     */
    public function getAbsoluteOutputFileDir(): string;

    /**
     * Return if development mode is enabled
     *
     * @return bool
     */
    public function isDevelopment(): bool;

    /**
     * Return the plugin level options
     *
     * @return mixed
     */
    public function getOptions();

    /**
     * Return the name of the compiled asset file or `NULL` if it should be generated automatically
     *
     * @return string|null
     */
    public function getOutputFileName(): ?string;

    /**
     * Return if experimental features are enabled
     *
     * @return bool
     */
    public function getEnableExperimentalFeatures(): bool;

    /**
     * Return configuration for LiveReload
     *
     * @return LiveReloadConfiguration
     */
    public function getLiveReloadConfiguration(): LiveReloadConfiguration;

    /**
     * Return if a debug-symlink should be created to the compiled output file
     *
     * @return bool
     */
    public function getCreateSymlink(): bool;

    /**
     * Return the map of filters for types
     *
     * @return array
     */
    public function getFilterForType(): array;

    /**
     * Return the registered filter binaries
     *
     * @return array
     */
    public function getFilterBinaries(): array;

    /**
     * Return if strict mode is enabled
     *
     * @return bool
     */
    public function getStrictModeEnabled(): bool;
}
