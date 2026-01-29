<?php

declare(strict_types=1);

namespace Cundd\Assetic\Service;

use Cundd\Assetic\Configuration;
use Cundd\Assetic\Utility\ProfilingUtility;
use Cundd\Assetic\ValueObject\FinalOutputFilePath;
use Cundd\Assetic\ValueObject\PathWithoutHash;
use Cundd\Assetic\ValueObject\Result;
use LogicException;
use UnexpectedValueException;

use function end;
use function file_exists;
use function hash_file;
use function is_readable;
use function sprintf;
use function strlen;
use function substr;

class OutputFileHashService
{
    private string $previousHashFromCache;

    /**
     * @var array<non-empty-string, bool>
     */
    private array $wasWritten;

    public function __construct(
        private readonly CacheManagerInterface $cacheManager,
        private readonly OutputFileFinderInterface $outputFileFinder,
    ) {
    }

    /**
     * @return Result<FinalOutputFilePath,UnexpectedValueException>
     */
    public function buildAndStoreFileHash(
        Configuration $configuration,
        PathWithoutHash $outputFilenameWithoutHash,
        string $hashAlgorithm = 'md5',
    ): Result {
        ProfilingUtility::start('Will create file hash');
        $compileDestinationPath = $outputFilenameWithoutHash->getAbsoluteUri();
        if (!is_readable($compileDestinationPath)) {
            ProfilingUtility::end();

            return Result::err(new UnexpectedValueException(
                'Compiled destination path can not be read'
            ));
        }

        $fileHash = hash_file($hashAlgorithm, $compileDestinationPath);
        if (false === $fileHash) {
            ProfilingUtility::end();

            return Result::err(new UnexpectedValueException(
                'Could not create hash of compiled destination path'
            ));
        }
        ProfilingUtility::end('Did create file hash');
        $this->storeHash($outputFilenameWithoutHash, $fileHash);

        $finalFileName = $outputFilenameWithoutHash->getFileName()
            . OutputFileService::NAME_PART_SEPARATOR
            . $fileHash . '.css';

        return Result::ok(FinalOutputFilePath::fromFileName($finalFileName, $configuration));
    }

    /**
     * Return the hash for the last compiled version of the output file
     *
     * Check if the cache contains an entry with the hash for the current
     * output file name. If no such entry exists, or the file with the read hash
     * does not exist, the directory will be searched for a matching file.
     *
     * Warning: Other running PHP processes also may have updated the hash in
     * between. This is not detected.
     *
     * @throws LogicException if the hash for the given output file was already
     *                        updated by this instance
     */
    public function getPreviousHash(
        PathWithoutHash $currentOutputFilenameWithoutHash,
    ): string {
        if (isset($this->wasWritten[$currentOutputFilenameWithoutHash->getAbsoluteUri()])) {
            throw new LogicException(
                sprintf(
                    'Data for the given output file was already updated. File path "%s"',
                    $currentOutputFilenameWithoutHash->getAbsoluteUri()
                ),
                9314654528
            );
        }
        $suffix = '.css';

        ProfilingUtility::start('Get previous hash from cache');
        $previousHash = $this->getCachedPreviousHash($currentOutputFilenameWithoutHash);
        $previousHashFilePath = $currentOutputFilenameWithoutHash->getAbsoluteUri()
            . OutputFileService::NAME_PART_SEPARATOR . $previousHash . $suffix;

        if ($previousHash && file_exists($previousHashFilePath)) {
            ProfilingUtility::end('Get previous hash from cache: Hit');

            return $previousHash;
        } else {
            ProfilingUtility::end('Get previous hash from cache: Miss');
        }

        ProfilingUtility::start('Find previous output files');
        $publicUri = $currentOutputFilenameWithoutHash->getPublicUri();
        $matchingFiles = $this->outputFileFinder->findPreviousOutputFiles($publicUri, $suffix);
        if (!$matchingFiles) {
            ProfilingUtility::end('Find previous output files: None found');

            return '';
        }

        ProfilingUtility::end('Find previous output files: Found ' . count($matchingFiles));
        $lastMatchingFile = end($matchingFiles);

        return substr($lastMatchingFile, strlen($publicUri) + 1, (-1 * strlen($suffix)));
    }

    public function storeHash(
        PathWithoutHash $outputFilenameWithoutHash,
        string $fileHash,
    ): void {
        $this->wasWritten[$outputFilenameWithoutHash->getAbsoluteUri()] = true;
        $this->cacheManager->setCache($outputFilenameWithoutHash, $fileHash);
    }

    private function getCachedPreviousHash(
        PathWithoutHash $currentOutputFilenameWithoutHash,
    ): string {
        if (!isset($this->previousHashFromCache)) {
            $cachedValue = $this->cacheManager->getCache($currentOutputFilenameWithoutHash);
            assert(is_scalar($cachedValue) || is_null($cachedValue));
            $this->previousHashFromCache = (string) $cachedValue;
        }

        return $this->previousHashFromCache;
    }
}
