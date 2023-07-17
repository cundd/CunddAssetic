<?php

declare(strict_types=1);

namespace Cundd\Assetic\Service;

use Cundd\Assetic\Utility\GeneralUtility as AsseticGeneralUtility;

use function filemtime;
use function glob;
use function usort;

class OutputFileFinder implements OutputFileFinderInterface
{
    /**
     * Return an array of previously filtered Asset files
     *
     * @param string $filePath
     * @param string $suffix
     * @return array
     */
    public function findPreviousOutputFiles(string $filePath, string $suffix = '.css'): array
    {
        AsseticGeneralUtility::profile('Will call glob for previous filtered Asset files');
        $matchingFiles = glob($filePath . '_' . '*' . $suffix);
        AsseticGeneralUtility::profile('Did call glob for previous filtered Asset files');

        // Glob will not return invalid symlinks
        if (!$matchingFiles) {
            return [];
        }

        AsseticGeneralUtility::profile('Will sort previous filtered Asset files by modification time');
        // Sort by mtime
        usort($matchingFiles, fn($a, $b) => filemtime($a) - filemtime($b));
        AsseticGeneralUtility::profile('Did sort previous filtered Asset files by modification time');

        return $matchingFiles;
    }
}
