<?php

declare(strict_types=1);

namespace Cundd\Assetic\Output;

use Cundd\Assetic\ValueObject\FilePath;
use Cundd\Assetic\ValueObject\FinalOutputFilePath;
use Cundd\Assetic\ValueObject\PathWithoutHash;

interface CacheManagerInterface
{
    /**
     * Return the cached final file path for the given input-path
     */
    public function getFinalFilePath(
        PathWithoutHash $path,
    ): ?FinalOutputFilePath;

    /**
     * Store the final file path for the given input-path
     */
    public function setFinalFilePath(
        PathWithoutHash $path,
        FilePath $finalPath,
    ): void;

    /**
     * Remove the cached final file path for the given input-path
     */
    public function clearFinalFilePath(PathWithoutHash $path): void;
}
