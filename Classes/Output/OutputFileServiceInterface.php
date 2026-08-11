<?php

declare(strict_types=1);

namespace Cundd\Assetic\Output;

use Cundd\Assetic\Configuration;
use Cundd\Assetic\ValueObject\PathWithoutHash;

interface OutputFileServiceInterface
{
    /**
     * Return the current output filename without the hash
     *
     * If an output file name is set in the configuration use it, otherwise
     * create it by combining the file names of the assets.
     */
    public function getPathWithoutHash(
        Configuration $configuration,
    ): PathWithoutHash;
}
