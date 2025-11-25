<?php

declare(strict_types=1);

namespace Cundd\Assetic\Service;

use Cundd\Assetic\ValueObject\PathWithoutHash;

interface CacheManagerInterface
{
    /**
     * Return the value for the given identifier in the cache
     */
    public function getCache(PathWithoutHash $identifier): mixed;

    /**
     * Stores the value for the given identifier in the cache
     *
     * @param mixed $value Value to store
     */
    public function setCache(PathWithoutHash $identifier, $value): void;

    /**
     * Remove the cached hash
     */
    public function clearHashCache(PathWithoutHash $currentOutputFilenameWithoutHash): void;
}
