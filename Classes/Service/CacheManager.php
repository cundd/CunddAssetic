<?php

declare(strict_types=1);

namespace Cundd\Assetic\Service;

use Cundd\Assetic\Utility\ConfigurationUtility;
use Cundd\Assetic\ValueObject\PathWoHash;
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

    /**
     * Return the value for the given identifier in the cache
     *
     * @param PathWoHash $identifier Identifier key
     */
    public function getCache(PathWoHash $identifier): mixed
    {
        $identifier = $this->prepareIdentifier($identifier);
        $cacheInstance = $this->getCacheInstance();

        return $cacheInstance ? $cacheInstance->get($identifier) : null;
    }

    /**
     * Stores the value for the given identifier in the cache
     *
     * @param PathWoHash $identifier Identifier key
     * @param mixed      $value      Value to store
     */
    public function setCache(PathWoHash $identifier, $value): void
    {
        $identifier = $this->prepareIdentifier($identifier);
        $cacheInstance = $this->getCacheInstance();
        if ($cacheInstance) {
            $tags = [];
            $lifetime = 60 * 60 * 24;

            $cacheInstance->set($identifier, $value, $tags, $lifetime);
        }
    }

    /**
     * Remove the cached hash
     */
    public function clearHashCache(PathWoHash $currentOutputFilenameWithoutHash): void
    {
        $this->setCache($currentOutputFilenameWithoutHash, '');
    }

    private function getCacheInstance(): ?FrontendInterface
    {
        try {
            return GeneralUtility::makeInstance(TYPO3CacheManager::class)->getCache('assetic_cache');
        } catch (NoSuchCacheException $e) {
            return null;
        }
    }

    private function prepareIdentifier(PathWoHash $identifier): string
    {
        return sha1(
            ConfigurationUtility::getDomainIdentifier()
            . '-' . self::CACHE_IDENTIFIER_HASH
            . '_' . $identifier->getFileName()
        );
    }
}
