<?php

declare(strict_types=1);

namespace Cundd\Assetic\Service;

use Cundd\Assetic\Configuration;
use Cundd\Assetic\ValueObject\FilePath;
use Cundd\Assetic\ValueObject\PathWithoutHash;

use function basename;
use function implode;

class OutputFileService implements OutputFileServiceInterface
{
    public const NAME_PART_SEPARATOR = '-';

    public function __construct(
        private readonly OutputFileHashService $outputFileHashService,
    ) {
    }

    public function getPathWithoutHash(Configuration $configuration): PathWithoutHash
    {
        // Get the output name from the configuration
        // If the custom `outputFileName` is defined nothing is added to the
        // filename, so users must prevent collisions
        if ($configuration->outputFileName) {
            return new PathWithoutHash(
                $configuration->outputFileName,
                $configuration->outputFileDir,
            );
        }

        $outputFileNameParts = [
            $configuration->site->getIdentifier(),
        ];

        // Loop through all configured stylesheets
        $stylesheets = $configuration->stylesheetConfigurations;
        foreach ($stylesheets as $assetKey => $stylesheet) {
            // If the current value of $stylesheet is an array it's the detailed
            // configuration of a stylesheet, not the stylesheet path itself
            if (is_string($stylesheet)) {
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
            implode(self::NAME_PART_SEPARATOR, $outputFileNameParts),
            $configuration->outputFileDir,
        );
    }

    public function getExpectedPathWithHash(
        Configuration $configuration,
        PathWithoutHash $outputFilenameWithoutHash,
    ): ?FilePath {
        $previousHash = $this->outputFileHashService->getPreviousHash($outputFilenameWithoutHash);

        if ($previousHash) {
            return FilePath::fromFileName(
                $outputFilenameWithoutHash->getFileName()
                    . self::NAME_PART_SEPARATOR
                    . $previousHash
                    . '.css',
                $configuration
            );
        } else {
            return null;
        }
    }
}
