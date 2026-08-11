<?php

declare(strict_types=1);

namespace Cundd\Assetic\BuildSystem\BuildStep;

use Cundd\Assetic\Configuration;
use Cundd\Assetic\Output\OutputFileFinderInterface;
use Cundd\Assetic\ValueObject\BuildState;
use Cundd\Assetic\ValueObject\BuildStateResult;
use Throwable;

/**
 * @implements BuildStepInterface<Throwable>
 */
final class CollectFilesToCleanUp implements BuildStepInterface
{
    public function __construct(
        private readonly OutputFileFinderInterface $outputFileFinder,
    ) {
    }

    public function process(
        Configuration $configuration,
        BuildState $currentState,
    ): BuildStateResult {
        $filesToCleanUp = $this->outputFileFinder->findPreviousOutputFiles(
            $currentState->getOutputFilePathWithoutHash()->getAbsoluteUri()
        );

        return BuildStateResult::ok($currentState->withFilesToCleanUp($filesToCleanUp));
    }
}
