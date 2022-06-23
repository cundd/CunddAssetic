<?php
declare(strict_types=1);

namespace Cundd\Assetic\Service;

use Cundd\Assetic\Configuration\ConfigurationProviderFactory;
use Cundd\Assetic\Configuration\ConfigurationProviderInterface;
use Cundd\Assetic\Utility\GeneralUtility as AsseticGeneralUtility;
use Cundd\Assetic\ValueObject\FinalOutputFilePath;
use Cundd\Assetic\ValueObject\PathWoHash;
use LogicException;
use function end;
use function file_exists;
use function hash_file;
use function sprintf;
use function strlen;
use function substr;

class OutputFileHashService
{
    private CacheManager $cacheManager;

    private ConfigurationProviderInterface $configurationProvider;

    private string $previousHashFromCache;

    private array $wasWritten;

    private OutputFileFinder $outputFileFinder;

    public function __construct(
        CacheManagerInterface $cacheManager,
        ConfigurationProviderFactory $configurationProviderFactory,
        OutputFileFinderInterface $outputFileFinder
    ) {
        $this->cacheManager = $cacheManager;
        $this->configurationProvider = $configurationProviderFactory->build();
        $this->outputFileFinder = $outputFileFinder;
    }

    /**
     * @param PathWoHash $outputFilenameWithoutHash
     * @param string     $hashAlgorithm
     * @return FinalOutputFilePath
     */
    public function buildAndStoreFileHash(
        PathWoHash $outputFilenameWithoutHash,
        string $hashAlgorithm = 'md5'
    ): FinalOutputFilePath {
        // $hashAlgorithm = 'crc32';
        // $hashAlgorithm = 'sha1';
        // $hashAlgorithm = 'md5';
        $compileDestinationPath = $outputFilenameWithoutHash->getAbsoluteUri();
        $fileHash = hash_file($hashAlgorithm, $compileDestinationPath);
        AsseticGeneralUtility::profile('Did create file hash');
        $this->storeHash($outputFilenameWithoutHash, $fileHash);

        $finalFileName = $outputFilenameWithoutHash->getFileName() . '_' . $fileHash . '.css';

        return FinalOutputFilePath::fromFileName($finalFileName, $this->configurationProvider);
    }

    /**
     * Return the hash for the last compiled version of the output file
     *
     * Check if the cache contains an entry with the hash for the current output file name. If no such entry exists, or
     * the file with the read hash does not exist, the directory will be searched for a matching file.
     *
     * Warning: Other running PHP processes also may have updated the hash in between. This is not detected
     *
     * @param PathWoHash $currentOutputFilenameWithoutHash
     * @return string
     * @throws LogicException if the hash for the given output file was already updated by this instance
     */
    public function getPreviousHash(PathWoHash $currentOutputFilenameWithoutHash): string
    {
        if (isset($this->wasWritten[$currentOutputFilenameWithoutHash->getAbsoluteUri()])) {
            throw new LogicException(
                sprintf(
                    'Data for the given output file was already updated. File path "%s"',
                    $currentOutputFilenameWithoutHash->getAbsoluteUri()
                )
            );
        }
        $suffix = '.css';
        $publicUri = $currentOutputFilenameWithoutHash->getPublicUri();

        $previousHash = $this->getCachedPreviousHash($currentOutputFilenameWithoutHash);
        $previousHashFilePath = $currentOutputFilenameWithoutHash->getAbsoluteUri() . '_' . $previousHash . $suffix;

        if ($previousHash && file_exists($previousHashFilePath)) {
            return $previousHash;
        }

        $matchingFiles = $this->outputFileFinder->findPreviousOutputFiles($publicUri, $suffix);
        if (!$matchingFiles) {
            return '';
        }
        $lastMatchingFile = end($matchingFiles);

        return substr($lastMatchingFile, strlen($publicUri) + 1, (-1 * strlen($suffix)));
    }

    public function storeHash(PathWoHash $outputFilenameWithoutHash, string $fileHash)
    {
        $this->wasWritten[$outputFilenameWithoutHash->getAbsoluteUri()] = true;
        $this->cacheManager->setCache($outputFilenameWithoutHash, $fileHash);
    }

    /**
     * @param PathWoHash $currentOutputFilenameWithoutHash
     * @return string
     */
    private function getCachedPreviousHash(PathWoHash $currentOutputFilenameWithoutHash): string
    {
        if (!isset($this->previousHashFromCache)) {
            $this->previousHashFromCache = (string)$this->cacheManager->getCache($currentOutputFilenameWithoutHash);
        }

        return $this->previousHashFromCache;
    }
}
