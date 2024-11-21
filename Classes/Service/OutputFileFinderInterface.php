<?php

declare(strict_types=1);

namespace Cundd\Assetic\Service;

interface OutputFileFinderInterface
{
    /**
     * Return an array of previously compiled Asset files
     *
     * @return string[]
     */
    public function findPreviousOutputFiles(string $filePath, string $suffix = '.css'): array;
}
