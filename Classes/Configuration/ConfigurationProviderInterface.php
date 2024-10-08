<?php

declare(strict_types=1);

namespace Cundd\Assetic\Configuration;

interface ConfigurationProviderInterface
{
    /**
     * Return the configurations for the stylesheets
     */
    public function getStylesheetConfigurations(): array;

    /**
     * Return if re-compilation is enabled for guests
     *
     * If `FALSE` the assets will only be re-compiled if a backend user is logged in
     */
    public function getAllowCompileWithoutLogin(): bool;

    /**
     * Return the path to the web directory
     */
    public function getPublicPath(): string;

    /**
     * Return the path to the output file directory relative to the web root
     */
    public function getOutputFileDir(): string;

    /**
     * Return the absolute path to the output file directory
     */
    public function getAbsoluteOutputFileDir(): string;

    /**
     * Return if development mode is enabled
     */
    public function isDevelopment(): bool;

    /**
     * Return the plugin level options
     */
    public function getOptions();

    /**
     * Return the name of the compiled asset file or `NULL` if it should be generated automatically
     */
    public function getOutputFileName(): ?string;

    /**
     * Return if experimental features are enabled
     */
    public function getEnableExperimentalFeatures(): bool;

    /**
     * Return configuration for LiveReload
     */
    public function getLiveReloadConfiguration(): LiveReloadConfiguration;

    /**
     * Return if a debug-symlink should be created to the compiled output file
     */
    public function getCreateSymlink(): bool;

    /**
     * Return the map of filters for types
     */
    public function getFilterForType(): array;

    /**
     * Return the registered filter binaries
     */
    public function getFilterBinaries(): array;

    /**
     * Return if strict mode is enabled
     */
    public function getStrictModeEnabled(): bool;
}
