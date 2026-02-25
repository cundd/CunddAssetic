<?php

declare(strict_types=1);

namespace Cundd\Assetic\Service;

use Cundd\Assetic\ValueObject\PathWithoutHash;
use TYPO3\CMS\Core\Cache\CacheManager as TYPO3CacheManager;
use TYPO3\CMS\Core\Cache\Exception\NoSuchCacheException;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

use function sha1;

class HashCacheManager implements HashCacheManagerInterface
{
    /**
     * Cache identifier for the hash
     */
    private const CACHE_IDENTIFIER_HASH = 'cundd_assetic_cache_identifier_hash';

    public function getCache(PathWithoutHash $path): mixed
    {
        $path = $this->prepareIdentifier($path);
        $cacheInstance = $this->getCacheInstance();

        return $cacheInstance?->get($path);
    }

    public function setCache(PathWithoutHash $path, string $hash): void
    {
        $path = $this->prepareIdentifier($path);
        $cacheInstance = $this->getCacheInstance();
        $cacheInstance?->set(
            $path,
            $hash,
            tags: [],
            lifetime: null
        );
    }

    public function clearHashCache(PathWithoutHash $path): void
    {
        $this->setCache($path, '');
    }

    private function getCacheInstance(): ?FrontendInterface
    {
        try {
            return GeneralUtility::makeInstance(TYPO3CacheManager::class)
                ->getCache('assetic_cache');
        } catch (NoSuchCacheException $e) {
            return null;
        }
    }

    private function prepareIdentifier(PathWithoutHash $identifier): string
    {
        return sha1(
            self::CACHE_IDENTIFIER_HASH
            . '_' . $identifier->getFileName()
        );
    }
}
