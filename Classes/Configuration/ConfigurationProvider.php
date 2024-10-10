<?php

declare(strict_types=1);

namespace Cundd\Assetic\Configuration;

use TYPO3\CMS\Core\Core\Environment;

use function php_sapi_name;

class ConfigurationProvider implements ConfigurationProviderInterface
{
    private array $configuration;

    private LiveReloadConfiguration $liveReloadConfiguration;

    private string $outputFileDir = 'typo3temp/cundd_assetic/';

    /**
     * @param array<string, mixed> $configuration
     */
    public function __construct(array $configuration)
    {
        $this->configuration = $configuration;
    }

    public function getStylesheetConfigurations(): array
    {
        return (array) $this->configuration['stylesheets.'];
    }

    public function getAllowCompileWithoutLogin(): bool
    {
        return (bool) ($this->configuration['allow_compile_without_login'] ?? false);
    }

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

    public function isDevelopment(): bool
    {
        if ('cli' === php_sapi_name()) {
            return true;
        }

        return (bool) ($this->configuration['development'] ?? false);
    }

    /**
     * @return array|null
     */
    public function getOptions(): mixed
    {
        return $this->configuration['options.'] ?? null;
    }

    public function getOutputFileName(): ?string
    {
        return $this->configuration['output'] ?? null;
    }

    public function getLiveReloadConfiguration(): LiveReloadConfiguration
    {
        if (!isset($this->liveReloadConfiguration)) {
            $rawLiveReloadConfiguration = $this->configuration['livereload.'] ?? [];

            $this->liveReloadConfiguration = new LiveReloadConfiguration(
                (int) ($rawLiveReloadConfiguration['port'] ?? 35729),
                (bool) ($rawLiveReloadConfiguration['add_javascript'] ?? false),
                (bool) ($rawLiveReloadConfiguration['skip_server_test'] ?? false)
            );
        }

        return $this->liveReloadConfiguration;
    }

    public function getCreateSymlink(): bool
    {
        return $this->configuration['create_symlink'] || $this->getLiveReloadConfiguration()->isEnabled();
    }

    public function getFilterForType(): array
    {
        return (array) $this->configuration['filter_for_type.'];
    }

    public function getFilterBinaries(): array
    {
        return (array) $this->configuration['filter_binaries.'];
    }

    public function getStrictModeEnabled(): bool
    {
        return (bool) ($this->configuration['strict'] ?? false);
    }
}
