<?php

declare(strict_types=1);

namespace Cundd\Assetic\Output;

use Cundd\Assetic\Configuration;
use Cundd\Assetic\Utility\ProfilingUtility;
use Cundd\Assetic\ValueObject\FilePath;
use Cundd\Assetic\ValueObject\FinalOutputFilePath;
use Cundd\Assetic\ValueObject\PathWithoutHash;

final class PreviousOutputFileService implements PreviousOutputFileServiceInterface
{
    public const NAME_PART_SEPARATOR = '-';

    public function __construct(
        private readonly CacheManagerInterface $cacheManager,
        private readonly OutputFileFinderInterface $outputFileFinder,
    ) {
    }

    public function getPreviousPathWithHash(
        Configuration $configuration,
        PathWithoutHash $outputFilenameWithoutHash,
    ): ?FilePath {
        $suffix = '.css';

        ProfilingUtility::start('Get previous hash from cache');
        $previousPath = $this->getCachedPreviousFilePath(
            $outputFilenameWithoutHash
        );

        if ($previousPath && $previousPath->isReadable()) {
            ProfilingUtility::end('Get previous hash from cache: Hit');

            return $previousPath;
        } else {
            ProfilingUtility::end('Get previous hash from cache: Miss');
        }

        ProfilingUtility::start('Find previous output files');
        $publicUri = $outputFilenameWithoutHash->getPublicUri();
        $matchingFiles = $this->outputFileFinder->findPreviousOutputFiles(
            $publicUri,
            $suffix
        );
        if (!$matchingFiles) {
            ProfilingUtility::end('Find previous output files: None found');

            return null;
        }

        ProfilingUtility::end('Find previous output files: Found ' . count($matchingFiles));
        $lastMatchingFile = end($matchingFiles);

        return new FinalOutputFilePath($lastMatchingFile);
    }

    private function getCachedPreviousFilePath(
        PathWithoutHash $currentOutputFilenameWithoutHash,
    ): ?FinalOutputFilePath {
        return $this->cacheManager->getFinalFilePath(
            $currentOutputFilenameWithoutHash
        );
    }
}
