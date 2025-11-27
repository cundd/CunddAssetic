<?php

declare(strict_types=1);

namespace Cundd\Assetic\Service;

use Cundd\Assetic\Configuration\ConfigurationProviderFactory;
use Cundd\Assetic\Configuration\ConfigurationProviderInterface;
use Cundd\Assetic\Utility\ConfigurationUtility;
use Cundd\Assetic\ValueObject\FilePath;
use Cundd\Assetic\ValueObject\PathWithoutHash;

use function basename;
use function implode;
use function is_array;

class OutputFileService implements OutputFileServiceInterface
{
    private readonly ConfigurationProviderInterface $configurationProvider;

    public function __construct(
        ConfigurationProviderFactory $configurationProviderFactory,
        private readonly OutputFileHashService $outputFileHashService,
    ) {
        $this->configurationProvider = $configurationProviderFactory->build();
    }

    public function getPathWithoutHash(): PathWithoutHash
    {
        // Get the output name from the configuration
        $absoluteDirectoryPath = $this->configurationProvider->getAbsoluteOutputFileDir();
        if ($this->configurationProvider->getOutputFileName()) {
            return new PathWithoutHash(
                ConfigurationUtility::getDomainIdentifier() . $this->configurationProvider->getOutputFileName(),
                $this->configurationProvider->getOutputFileDir(),
                $absoluteDirectoryPath
            );
        }

        $outputFileNameParts = [];

        // Loop through all configured stylesheets
        $stylesheets = $this->configurationProvider->getStylesheetConfigurations();
        foreach ($stylesheets as $assetKey => $stylesheet) {
            // If the current value of $stylesheet is an array it's the detailed
            // configuration of a stylesheet, not the stylesheet path itself
            if (!is_array($stylesheet)) {
                $stylesheetFileName = basename($stylesheet);
                $stylesheetFileName = str_replace(
                    ['.css', '.scss', '.sass', '.less'],
                    '',
                    $stylesheetFileName
                );
                $stylesheetFileName = preg_replace(
                    '![^0-9a-zA-Z-_]!',
                    '',
                    $stylesheetFileName
                );
                $outputFileNameParts[] = $stylesheetFileName;
            }
        }

        return new PathWithoutHash(
            ConfigurationUtility::getDomainIdentifier() . implode('_', $outputFileNameParts),
            $this->configurationProvider->getOutputFileDir(),
            $absoluteDirectoryPath
        );
    }

    public function getExpectedPathWithHash(PathWithoutHash $outputFilenameWithoutHash): ?FilePath
    {
        $previousHash = $this->outputFileHashService->getPreviousHash($outputFilenameWithoutHash);

        if ($previousHash) {
            return FilePath::fromFileName(
                $outputFilenameWithoutHash->getFileName() . '_' . $previousHash . '.css',
                $this->configurationProvider
            );
        } else {
            return null;
        }
    }
}
