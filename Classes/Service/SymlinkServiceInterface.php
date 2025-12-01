<?php

declare(strict_types=1);

namespace Cundd\Assetic\Service;

use Cundd\Assetic\Configuration;
use Cundd\Assetic\ValueObject\FilePath;
use Cundd\Assetic\ValueObject\PathWithoutHash;

interface SymlinkServiceInterface
{
    /**
     * Create the symlink to the given final path
     */
    public function createSymlinkToFinalPath(
        Configuration $configuration,
        FilePath $fileFinalPath,
        PathWithoutHash $outputFilePathWithoutHash,
    ): ?FilePath;

    /**
     * Remove the symlink
     */
    public function removeSymlink(
        Configuration $configuration,
        PathWithoutHash $outputFilePathWithoutHash,
    ): void;

    /**
     * Return the symlink URI
     */
    public function getSymlinkPath(
        Configuration $configuration,
        PathWithoutHash $outputFilePathWithoutHash,
    ): FilePath;
}
