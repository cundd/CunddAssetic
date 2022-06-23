<?php
declare(strict_types=1);

namespace Cundd\Assetic\Configuration;

use TYPO3\CMS\Core\Core\Environment;
use function php_sapi_name;

class ConfigurationProvider implements ConfigurationProviderInterface
{
    /**
     * @var array
     */
    private $configuration;

    /**
     * @var LiveReloadConfiguration
     */
    private $liveReloadConfiguration;

    /**
     * @var string
     */
    private $outputFileDir = 'typo3temp/cundd_assetic/';

    /**
     * Configuration Provider constructor
     *
     * @param array $configuration
     */
    public function __construct(array $configuration)
    {
        $this->configuration = $configuration;
    }

    /**
     * Return the configurations for the stylesheets
     *
     * @return array
     */
    public function getStylesheetConfigurations(): array
    {
        return (array)$this->configuration['stylesheets.'];
    }

    /**
     * Return if re-compilation is enabled for guests
     *
     * If `FALSE` the assets will only be re-compiled if a backend user is logged in
     *
     * @return bool
     */
    public function getAllowCompileWithoutLogin(): bool
    {
        return (bool)($this->configuration['allow_compile_without_login'] ?? false);
    }

    /**
     * Return the path to the web directory
     *
     * @return string
     */
    public function getPublicPath(): string
    {
        return Environment::getPublicPath() . '/';
    }

    public function getOutputFileDir(): string
    {
        return $this->outputFileDir;
    }

    public function getAbsoluteOutputFileDir(): string
    {
        return $this->getPublicPath() . $this->getOutputFileDir();
    }

    /**
     * Return if development mode is enabled
     *
     * @return bool
     */
    public function isDevelopment(): bool
    {
        if (php_sapi_name() === 'cli') {
            return true;
        }

        return (bool)($this->configuration['development'] ?? false);
    }

    /**
     * Return the plugin level options
     *
     * @return mixed
     */
    public function getOptions()
    {
        return $this->configuration['options.'] ?? null;
    }

    /**
     * Return the name of the compiled asset file or `NULL` if it should be generated automatically
     *
     * @return string|null
     */
    public function getOutputFileName(): ?string
    {
        return $this->configuration['output'] ?? null;
    }

    /**
     * Return if experimental features are enabled
     *
     * @return bool
     */
    public function getEnableExperimentalFeatures(): bool
    {
        return $this->getLiveReloadConfiguration()->getAddJavascript()
            || (bool)($this->configuration['experimental'] ?? false);
    }

    /**
     * Return configuration for LiveReload
     *
     * @return LiveReloadConfiguration
     */
    public function getLiveReloadConfiguration(): LiveReloadConfiguration
    {
        if (!$this->liveReloadConfiguration) {
            $rawLiveReloadConfiguration = $this->configuration['livereload.'] ?? [];

            $this->liveReloadConfiguration = new LiveReloadConfiguration(
                (int)($rawLiveReloadConfiguration['port'] ?? 35729),
                (bool)($rawLiveReloadConfiguration['add_javascript'] ?? false),
                (bool)($rawLiveReloadConfiguration['skip_server_test'] ?? false)
            );
        }

        return $this->liveReloadConfiguration;
    }

    /**
     * Return if a debug-symlink should be created to the compiled output file
     *
     * @return bool
     */
    public function getCreateSymlink(): bool
    {
        return $this->configuration['create_symlink'] || $this->getEnableExperimentalFeatures();
    }

    /**
     * Return the map of filters for types
     *
     * @return array
     */
    public function getFilterForType(): array
    {
        return (array)$this->configuration['filter_for_type.'];
    }

    /**
     * Return the registered filter binaries
     *
     * @return array
     */
    public function getFilterBinaries(): array
    {
        return (array)$this->configuration['filter_binaries.'];
    }

    /**
     * Return if strict mode is enabled
     *
     * @return bool
     */
    public function getStrictModeEnabled(): bool
    {
        return (bool)($this->configuration['strict'] ?? false);
    }
}
