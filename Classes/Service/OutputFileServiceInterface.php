<?php

declare(strict_types=1);

namespace Cundd\Assetic\Service;

use Cundd\Assetic\ValueObject\FilePath;
use Cundd\Assetic\ValueObject\PathWithoutHash;

interface OutputFileServiceInterface
{
    /**
     * Return the current output filename without the hash
     *
     * If an output file name is set in the configuration use it, otherwise
     * create it by combining the file names of the assets.
     */
    public function getPathWithoutHash(): PathWithoutHash;

    /**
     * Return the expected final File Path
     */
    public function getExpectedPathWithHash(
        PathWithoutHash $outputFilenameWithoutHash,
    ): ?FilePath;
}
