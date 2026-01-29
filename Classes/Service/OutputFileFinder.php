<?php

declare(strict_types=1);

namespace Cundd\Assetic\Service;

use Cundd\Assetic\Utility\ProfilingUtility;

use function filemtime;
use function glob;
use function usort;

class OutputFileFinder implements OutputFileFinderInterface
{
    public function findPreviousOutputFiles(string $filePath, string $suffix = '.css'): array
    {
        ProfilingUtility::profile('Will call glob for previous filtered Asset files');
        $matchingFiles = glob($filePath . OutputFileService::NAME_PART_SEPARATOR . '*' . $suffix);
        ProfilingUtility::profile('Did call glob for previous filtered Asset files');

        // Glob will not return invalid symlinks
        if (!$matchingFiles) {
            return [];
        }

        ProfilingUtility::profile('Will sort previous filtered Asset files by modification time');
        // Sort by mtime
        usort($matchingFiles, fn ($a, $b) => filemtime($a) - filemtime($b));
        ProfilingUtility::profile('Did sort previous filtered Asset files by modification time');

        return $matchingFiles;
    }
}
