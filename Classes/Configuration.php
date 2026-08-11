<?php

declare(strict_types=1);

namespace Cundd\Assetic;

use Assetic\Contracts\Filter\FilterInterface;
use Cundd\Assetic\Configuration\LiveReloadConfiguration;
use Cundd\Assetic\Configuration\StylesheetConfiguration;
use TYPO3\CMS\Core\Site\Entity\Site;

final class Configuration
{
    public const OUTPUT_FILE_DIR = '/typo3temp/cundd_assetic/';

    /**
     * @param StylesheetConfiguration[]                           $stylesheetConfigurations
     * @param non-empty-string                                    $outputFileDir
     * @param array<string, class-string<FilterInterface>|'none'> $filterForType
     * @param array<string, string>                               $filterBinaries
     */
    public function __construct(
        public readonly Site $site,
        public readonly array $stylesheetConfigurations,
        public readonly string $outputFileDir,
        public readonly ?string $outputFileName,
        public readonly array $filterForType,
        public readonly array $filterBinaries,
        public readonly LiveReloadConfiguration $liveReloadConfiguration,
        public readonly bool $isDevelopment,
        public readonly bool $createSymlink,
        public readonly bool $allowDeveloperFeaturesWithoutLogin,
        public readonly bool $strictModeEnabled,
    ) {
    }

    public function withIsDevelopment(bool $isDevelopment): self
    {
        return new self(
            site: $this->site,
            stylesheetConfigurations: $this->stylesheetConfigurations,
            outputFileDir: $this->outputFileDir,
            outputFileName: $this->outputFileName,
            filterForType: $this->filterForType,
            filterBinaries: $this->filterBinaries,
            liveReloadConfiguration: $this->liveReloadConfiguration,
            isDevelopment: $isDevelopment,
            createSymlink: $this->createSymlink,
            allowDeveloperFeaturesWithoutLogin: $this->allowDeveloperFeaturesWithoutLogin,
            strictModeEnabled: $this->strictModeEnabled,
        );
    }

    public function withCreateSymlink(bool $createSymlink): self
    {
        return new self(
            site: $this->site,
            stylesheetConfigurations: $this->stylesheetConfigurations,
            outputFileDir: $this->outputFileDir,
            outputFileName: $this->outputFileName,
            filterForType: $this->filterForType,
            filterBinaries: $this->filterBinaries,
            liveReloadConfiguration: $this->liveReloadConfiguration,
            isDevelopment: $this->isDevelopment,
            createSymlink: $createSymlink,
            allowDeveloperFeaturesWithoutLogin: $this->allowDeveloperFeaturesWithoutLogin,
            strictModeEnabled: $this->strictModeEnabled,
        );
    }
}
