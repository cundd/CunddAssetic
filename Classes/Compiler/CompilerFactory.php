<?php

declare(strict_types=1);

namespace Cundd\Assetic\Compiler;

use Cundd\Assetic\Configuration\ConfigurationProviderFactory;
use Cundd\Assetic\Configuration\ConfigurationProviderInterface;

class CompilerFactory
{
    private readonly ConfigurationProviderInterface $configurationProvider;

    public function __construct(
        ConfigurationProviderFactory $configurationProviderFactory,
    ) {
        $this->configurationProvider = $configurationProviderFactory->build();
    }

    public function build(): CompilerInterface
    {
        return new Compiler($this->configurationProvider, $this->getPluginLevelOptions());
    }

    /**
     * Return the "options" configuration from the TypoScript of the current page
     *
     * @return array<string,mixed>
     */
    private function getPluginLevelOptions(): array
    {
        // Get the options
        $pluginLevelOptions = $this->configurationProvider->getOptions() ?? [];

        // Check for the development mode
        $pluginLevelOptions['debug'] = $this->configurationProvider->isDevelopment();

        return $pluginLevelOptions;
    }
}
