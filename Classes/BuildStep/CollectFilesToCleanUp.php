<?php
declare(strict_types=1);

namespace Cundd\Assetic\BuildStep;

use Cundd\Assetic\Service\OutputFileFinder;
use Cundd\Assetic\ValueObject\BuildState;
use Cundd\Assetic\ValueObject\BuildStateResult;

class CollectFilesToCleanUp implements BuildStepInterface
{
    private OutputFileFinder $outputFileFinder;

    public function __construct(OutputFileFinder $outputFileFinder)
    {
        $this->outputFileFinder = $outputFileFinder;
    }

    public function process(BuildState $currentState): BuildStateResult
    {
        $filesToCleanUp = $this->outputFileFinder->findPreviousOutputFiles(
            $currentState->getOutputFilePathWithoutHash()->getAbsoluteUri()
        );

        return BuildStateResult::ok($currentState->withFilesToCleanUp($filesToCleanUp));
    }
}
