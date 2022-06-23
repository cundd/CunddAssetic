<?php
declare(strict_types=1);

namespace Cundd\Assetic\Service;

interface OutputFileFinderInterface
{
    /**
     * Return an array of previously filtered Asset files
     *
     * @param string $filePath
     * @param string $suffix
     * @return array
     */
    public function findPreviousOutputFiles(string $filePath, string $suffix = '.css'): array;
}
