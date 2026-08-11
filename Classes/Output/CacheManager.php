<?php

declare(strict_types=1);

namespace Cundd\Assetic\Output;

use Cundd\Assetic\ValueObject\FilePath;
use Cundd\Assetic\ValueObject\FinalOutputFilePath;
use Cundd\Assetic\ValueObject\PathWithoutHash;
use TYPO3\CMS\Core\Cache\CacheManager as TYPO3CacheManager;
use TYPO3\CMS\Core\Cache\Exception\NoSuchCacheException;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;

use function sha1;

final class CacheManager implements CacheManagerInterface
{
    private const CACHE_IDENTIFIER_PATH = 'cundd_assetic_cache_identifier_path';

    private ?FrontendInterface $cacheInstance;

    public function __construct(TYPO3CacheManager $cacheManager)
    {
        try {
            $this->cacheInstance = $cacheManager->getCache('assetic_cache');
        } catch (NoSuchCacheException $e) {
            $this->cacheInstance = null;
        }
    }

    public function getFinalFilePath(
        PathWithoutHash $path,
    ): ?FinalOutputFilePath {
        $identifier = $this->prepareIdentifier($path);

        $finalFilePath = $this->cacheInstance?->get($identifier);
        if ($finalFilePath) {
            return new FinalOutputFilePath($finalFilePath);
        } else {
            return null;
        }
    }

    public function setFinalFilePath(
        PathWithoutHash $path,
        FilePath $finalPath,
    ): void {
        $identifier = $this->prepareIdentifier($path);
        $this->cacheInstance?->set(
            $identifier,
            $finalPath->getPublicUri(),
            tags: [],
            lifetime: null
        );
    }

    public function clearFinalFilePath(PathWithoutHash $path): void
    {
        $identifier = $this->prepareIdentifier($path);
        $this->cacheInstance?->remove($identifier);
    }

    private function prepareIdentifier(PathWithoutHash $path): string
    {
        return sha1(
            self::CACHE_IDENTIFIER_PATH
            . '_' . $path->getFileName()
        );
    }
}
