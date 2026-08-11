<?php

declare(strict_types=1);

namespace Cundd\Assetic\Service;

use Cundd\Assetic\ValueObject\PathWithoutHash;
use TYPO3\CMS\Core\Cache\CacheManager as TYPO3CacheManager;
use TYPO3\CMS\Core\Cache\Exception\NoSuchCacheException;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;

use function sha1;

class HashCacheManager implements HashCacheManagerInterface
{
    /**
     * Cache identifier for the hash
     */
    private const CACHE_IDENTIFIER_HASH = 'cundd_assetic_cache_identifier_hash';

    private ?FrontendInterface $cacheInstance;

    public function __construct(TYPO3CacheManager $cacheManager)
    {
        try {
            $this->cacheInstance = $cacheManager->getCache('assetic_cache');
        } catch (NoSuchCacheException $e) {
            $this->cacheInstance = null;
        }
    }

    public function getCache(PathWithoutHash $path): mixed
    {
        $path = $this->prepareIdentifier($path);

        return $this->cacheInstance?->get($path);
    }

    public function setCache(PathWithoutHash $path, string $hash): void
    {
        $path = $this->prepareIdentifier($path);
        $this->cacheInstance?->set(
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

    private function prepareIdentifier(PathWithoutHash $identifier): string
    {
        return sha1(
            self::CACHE_IDENTIFIER_HASH
            . '_' . $identifier->getFileName()
        );
    }
}
