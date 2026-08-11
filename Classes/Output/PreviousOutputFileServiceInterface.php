<?php

declare(strict_types=1);

namespace Cundd\Assetic\Output;

use Cundd\Assetic\Configuration;
use Cundd\Assetic\ValueObject\FilePath;
use Cundd\Assetic\ValueObject\PathWithoutHash;

interface PreviousOutputFileServiceInterface
{
    /**
     * Return the final File Path from the last compilation run
     */
    public function getPreviousPathWithHash(
        Configuration $configuration,
        PathWithoutHash $outputFilenameWithoutHash,
    ): ?FilePath;
}
