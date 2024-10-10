<?php

declare(strict_types=1);

namespace Cundd\Assetic\Service;

use Cundd\Assetic\ValueObject\FilePath;
use Cundd\Assetic\ValueObject\PathWoHash;

interface SymlinkServiceInterface
{
    /**
     * Create the symlink to the given final path
     */
    public function createSymlinkToFinalPath(FilePath $fileFinalPath, PathWoHash $outputFilePathWithoutHash): ?FilePath;

    /**
     * Remove the symlink
     */
    public function removeSymlink(PathWoHash $outputFilePathWithoutHash): void;

    /**
     * Return the symlink URI
     */
    public function getSymlinkPath(PathWoHash $outputFilePathWithoutHash): FilePath;
}
