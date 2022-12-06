<?php
declare(strict_types=1);

namespace Cundd\Assetic\BuildStep;

use Cundd\Assetic\Exception\OutputFileException;
use Cundd\Assetic\Service\OutputFileHashService;
use Cundd\Assetic\Utility\GeneralUtility as AsseticGeneralUtility;
use Cundd\Assetic\Utility\PathUtility;
use Cundd\Assetic\ValueObject\BuildState;
use Cundd\Assetic\ValueObject\BuildStateResult;
use function clearstatcache;
use function is_link;
use function rename;
use function sprintf;
use function unlink;

class AddHashToFileName implements BuildStepInterface
{
    private OutputFileHashService $outputFileHashService;

    public function __construct(OutputFileHashService $outputFileHashService)
    {
        $this->outputFileHashService = $outputFileHashService;
    }

    public function process(BuildState $currentState): BuildStateResult
    {
        $outputFilenameWithoutHash = $currentState->getOutputFilePathWithoutHash();

        // Create the file hash and store it in the cache
        AsseticGeneralUtility::profile('Will create file hash');

        $finalFileNameResult = $this->outputFileHashService->buildAndStoreFileHash($outputFilenameWithoutHash);
        if ($finalFileNameResult->isErr()) {
            $finalFileNameErr = $finalFileNameResult->unwrapErr();

            return BuildStateResult::err(
                new OutputFileException(
                    $finalFileNameErr->getMessage(),
                    $finalFileNameErr->getCode(),
                    $finalFileNameErr
                )
            );
        }
        $finalFileName = $finalFileNameResult->unwrap();
        $outputFileFinalPath = $finalFileName->getAbsoluteUri();

        // Move the temp file to the new file
        AsseticGeneralUtility::profile('Will move compiled asset');

        clearstatcache(true, $outputFileFinalPath);
        if (is_link($outputFileFinalPath) && !unlink($outputFileFinalPath)) {
            return BuildStateResult::err(
                new OutputFileException(
                    sprintf('Output file "%s" already exists', $outputFileFinalPath)
                )
            );
        }

        $compileDestinationPath = $outputFilenameWithoutHash->getAbsoluteUri();

        if (!rename($compileDestinationPath, $outputFileFinalPath)) {
            $reason = PathUtility::getReasonForWriteFailure($outputFileFinalPath);

            return BuildStateResult::err(
                new OutputFileException(
                    sprintf(
                        'Could not rename temporary output file. Source: "%s", destination: "%s" because %s',
                        $compileDestinationPath,
                        $outputFileFinalPath,
                        $reason
                    )
                )
            );
        }
        AsseticGeneralUtility::profile('Did move compiled asset');

        return BuildStateResult::ok($currentState->withFilePath($finalFileName));
    }
}
