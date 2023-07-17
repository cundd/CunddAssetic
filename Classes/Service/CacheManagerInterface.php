<?php

declare(strict_types=1);

namespace Cundd\Assetic\Service;

use Cundd\Assetic\ValueObject\PathWoHash;

interface CacheManagerInterface
{
    /**
     * Return the value for the given identifier in the cache
     *
     * @param PathWoHash $identifier Identifier key
     * @return mixed
     */
    public function getCache(PathWoHash $identifier);

    /**
     * Stores the value for the given identifier in the cache
     *
     * @param PathWoHash $identifier Identifier key
     * @param mixed      $value      Value to store
     */
    public function setCache(PathWoHash $identifier, $value);

    /**
     * Remove the cached hash
     *
     * @param PathWoHash $currentOutputFilenameWithoutHash
     * @return void
     */
    public function clearHashCache(PathWoHash $currentOutputFilenameWithoutHash): void;
}
