<?php

declare(strict_types=1);

namespace Cundd\Assetic\Service;

use Cundd\Assetic\ValueObject\PathWithoutHash;

interface HashCacheManagerInterface
{
    /**
     * Return the hash for the given path if one exists in the cache
     */
    public function getCache(PathWithoutHash $path): mixed;

    /**
     * Store the hash for the given path in the cache
     */
    public function setCache(PathWithoutHash $path, string $hash): void;

    /**
     * Remove the cached hash for the given path
     */
    public function clearHashCache(PathWithoutHash $path): void;
}
