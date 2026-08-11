<?php

declare(strict_types=1);

namespace Cundd\Assetic\Output;

use Cundd\Assetic\Configuration;
use Cundd\Assetic\Utility\ProfilingUtility;
use Cundd\Assetic\ValueObject\FinalOutputFilePath;
use Cundd\Assetic\ValueObject\PathWithoutHash;
use Cundd\Assetic\ValueObject\Result;
use UnexpectedValueException;

use function hash_file;
use function is_readable;

final class OutputFileHashService
{
    /**
     * @return Result<FinalOutputFilePath,UnexpectedValueException>
     */
    public function buildFilenameWithHash(
        Configuration $configuration,
        PathWithoutHash $outputFilenameWithoutHash,
    ): Result {
        ProfilingUtility::start('Will create file hash');
        $compileDestinationPath = $outputFilenameWithoutHash->getAbsoluteUri();
        if (!is_readable($compileDestinationPath)) {
            ProfilingUtility::end();

            return Result::err(new UnexpectedValueException(sprintf(
                'Compiled destination path "%s" can not be read',
                $compileDestinationPath
            )));
        }

        $fileHash = hash_file('md5', $compileDestinationPath);
        if (false === $fileHash) {
            ProfilingUtility::end();

            return Result::err(new UnexpectedValueException(
                'Could not create hash of compiled destination path'
            ));
        }

        ProfilingUtility::end('Did create file hash');

        $finalFileName = $outputFilenameWithoutHash->getFileName()
            . OutputFileService::NAME_PART_SEPARATOR
            . $fileHash . '.css';

        return Result::ok(
            FinalOutputFilePath::fromFileName($finalFileName, $configuration)
        );
    }
}
