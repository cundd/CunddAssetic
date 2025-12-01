<?php

declare(strict_types=1);

namespace Cundd\Assetic\Service;

use Cundd\Assetic\ValueObject\PathWithoutHash;
use TYPO3\CMS\Core\Cache\CacheManager as TYPO3CacheManager;
use TYPO3\CMS\Core\Cache\Exception\NoSuchCacheException;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

use function sha1;

class CacheManager implements CacheManagerInterface
{
    /**
     * Cache identifier for the hash
     */
    private const CACHE_IDENTIFIER_HASH = 'cundd_assetic_cache_identifier_hash';

    public function getCache(PathWithoutHash $identifier): mixed
    {
        $identifier = $this->prepareIdentifier($identifier);
        $cacheInstance = $this->getCacheInstance();

        return $cacheInstance ? $cacheInstance->get($identifier) : null;
    }

    public function setCache(PathWithoutHash $identifier, $value): void
    {
        $identifier = $this->prepareIdentifier($identifier);
        $cacheInstance = $this->getCacheInstance();
        if ($cacheInstance) {
            $tags = [];
            $lifetime = 60 * 60 * 24;

            $cacheInstance->set($identifier, $value, $tags, $lifetime);
        }
    }

    public function clearHashCache(
        PathWithoutHash $currentOutputFilenameWithoutHash,
    ): void {
        $this->setCache($currentOutputFilenameWithoutHash, '');
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
