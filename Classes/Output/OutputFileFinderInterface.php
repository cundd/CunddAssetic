<?php

declare(strict_types=1);

namespace Cundd\Assetic\Output;

interface OutputFileFinderInterface
{
    /**
     * Return an array of previously compiled Asset files
     *
     * @return non-empty-string[]
     */
    public function findPreviousOutputFiles(
        string $filePath,
        string $suffix = '.css',
    ): array;
}
