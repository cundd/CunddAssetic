<?php

declare(strict_types=1);

namespace Cundd\Assetic\BuildSystem;

use Cundd\Assetic\BuildSystem\BuildStep\BuildStepInterface;
use Cundd\Assetic\Compiler\CompilerFactory;
use Cundd\Assetic\Compiler\CompilerInterface;
use Cundd\Assetic\Configuration;
use Cundd\Assetic\Output\OutputFileFinderInterface;
use Cundd\Assetic\Output\OutputFileHashService;
use Cundd\Assetic\Output\OutputFileServiceInterface;
use Cundd\Assetic\Output\SymlinkServiceInterface;
use Cundd\Assetic\Utility\ProfilingUtility;
use Cundd\Assetic\ValueObject\BuildState;
use Cundd\Assetic\ValueObject\BuildStateResult;
use Throwable;

final class Executor implements ExecutorInterface
{
    private CompilerInterface $compiler;

    public function __construct(
        CompilerFactory $compilerFactory,
        private readonly OutputFileHashService $outputFileHashService,
        private readonly OutputFileServiceInterface $outputFileService,
        private readonly SymlinkServiceInterface $symlinkService,
        private readonly OutputFileFinderInterface $outputFileFinder,
    ) {
        $this->compiler = $compilerFactory->build();
    }

    /**
     * Collect and compile Assets and return a Result with the path to the compiled stylesheet
     *
     * @return BuildStateResult<covariant Throwable>
     */
    public function build(
        Configuration $configuration,
    ): BuildStateResult {
        ProfilingUtility::start('Will compile assets');

        $outputFilePathWithoutHash = $this->outputFileService
            ->getPathWithoutHash($configuration);

        $currentState = new BuildState(
            $outputFilePathWithoutHash,
            $outputFilePathWithoutHash,
            []
        );

        $buildSteps = $this->getBuildSteps($configuration);
        assert(count($buildSteps) > 0);
        foreach ($buildSteps as $buildStep) {
            ProfilingUtility::start('Will process build step ' . get_class($buildStep));
            $currentStateResult = $buildStep->process($configuration, $currentState);
            ProfilingUtility::end('Did process build step ' . get_class($buildStep));
            if ($currentStateResult->isErr()) {
                return $currentStateResult;
            }
            $currentState = $currentStateResult->unwrap();
        }

        ProfilingUtility::end('Did compile assets');

        return $currentStateResult;
    }

    /**
     * @return BuildStepInterface<covariant Throwable>[]
     */
    private function getBuildSteps(Configuration $configuration): array
    {
        $buildSteps = [
            // Collect old compiled files to clean up
            new BuildStep\CollectFilesToCleanUp($this->outputFileFinder),

            // Remove old symlinks
            new BuildStep\RemoveOldSymlinks($this->symlinkService),

            // Compile
            new BuildStep\Compile($this->compiler),

            // Patch extension paths
            new BuildStep\PatchExtensionPath(),

            // Clean up old files
            new BuildStep\CleanUpOldFiles(),

            // Build hashed file
            new BuildStep\AddHashToFileName($this->outputFileHashService),
        ];

        if ($configuration->createSymlink) {
            // Create new symlink
            $buildSteps[] = new BuildStep\CreateNewSymlink($this->symlinkService);
        }

        return $buildSteps;
    }
}
