<?php

declare(strict_types=1);

namespace Cundd\Assetic\Output;

use Cundd\Assetic\Configuration;
use Cundd\Assetic\ValueObject\PathWithoutHash;

use function basename;
use function implode;

final class OutputFileService implements OutputFileServiceInterface
{
    public const NAME_PART_SEPARATOR = '-';

    public function getPathWithoutHash(
        Configuration $configuration,
    ): PathWithoutHash {
        // Get the output name from the configuration
        // If the custom `outputFileName` is defined nothing is added to the
        // filename, so users must prevent collisions
        if ($configuration->outputFileName) {
            return PathWithoutHash::fromFileName(
                $configuration->outputFileName,
                $configuration
            );
        }

        // Add the `Site`'s identifier to the name to prevent collisions
        $outputFileNameParts = [
            $configuration->site->getIdentifier(),
        ];

        // Loop through all configured stylesheets
        $stylesheets = $configuration->stylesheetConfigurations;
        foreach ($stylesheets as $stylesheet) {
            $stylesheetPath = $stylesheet->file;
            $stylesheetFileName = basename($stylesheetPath);
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

        return PathWithoutHash::fromFileName(
            implode(self::NAME_PART_SEPARATOR, $outputFileNameParts),
            $configuration
        );
    }
}
