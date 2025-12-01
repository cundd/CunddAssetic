<?php

declare(strict_types=1);

namespace Cundd\Assetic\BuildStep;

use Cundd\Assetic\Configuration;
use Cundd\Assetic\Service\OutputFileFinderInterface;
use Cundd\Assetic\ValueObject\BuildState;
use Cundd\Assetic\ValueObject\BuildStateResult;
use Throwable;

/**
 * @implements BuildStepInterface<Throwable>
 */
class CollectFilesToCleanUp implements BuildStepInterface
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
