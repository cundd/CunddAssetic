<?php

declare(strict_types=1);

namespace Cundd\Assetic;

use Cundd\Assetic\BuildStep\BuildStepInterface;
use Cundd\Assetic\Compiler\CompilerFactory;
use Cundd\Assetic\Compiler\CompilerInterface;
use Cundd\Assetic\Service\HashCacheManagerInterface;
use Cundd\Assetic\Service\OutputFileFinderInterface;
use Cundd\Assetic\Service\OutputFileHashService;
use Cundd\Assetic\Service\OutputFileServiceInterface;
use Cundd\Assetic\Service\SymlinkServiceInterface;
use Cundd\Assetic\Utility\ProfilingUtility;
use Cundd\Assetic\ValueObject\BuildState;
use Cundd\Assetic\ValueObject\CompilationContext;
use Cundd\Assetic\ValueObject\FilePath;
use Cundd\Assetic\ValueObject\ManagerResultInfo;
use Cundd\Assetic\ValueObject\Result;
use Throwable;

use function file_exists;

/**
 * Assetic Manager
 */
class Manager implements ManagerInterface
{
    private readonly CompilerInterface $compiler;

    public function __construct(
        CompilerFactory $compilerFactory,
        private readonly HashCacheManagerInterface $cacheManager,
        private readonly OutputFileHashService $outputFileHashService,
        private readonly OutputFileServiceInterface $outputFileService,
        private readonly SymlinkServiceInterface $symlinkService,
        private readonly OutputFileFinderInterface $outputFileFinder,
    ) {
        $this->compiler = $compilerFactory->build();
    }

    public function collectAndCompile(
        Configuration $configuration,
        CompilationContext $compilationContext,
    ): Result {
        // Check if the assets should be compiled
        if ($this->willCompile($configuration, $compilationContext)) {
            return $this->collectAssetsAndCompile(
                $configuration,
                $compilationContext
            )->map(
                fn (FilePath $f) => new ManagerResultInfo($f, usedExistingFile: false)
            );
        }

        $pathWithoutHash = $this->outputFileService
            ->getPathWithoutHash($configuration);
        $expectedPath = $this->outputFileService
            ->getExpectedPathWithHash($configuration, $pathWithoutHash);
        if ($expectedPath && file_exists($expectedPath->getAbsoluteUri())) {
            return Result::ok(
                new ManagerResultInfo($expectedPath, usedExistingFile: true)
            );
        }

        // If the expected output file does not exist clear the internal cache,
        // force compilation and call the main routine again
        $newCompilationContext = new CompilationContext(
            site: $compilationContext->site,
            isBackendUserLoggedIn: $compilationContext->isBackendUserLoggedIn,
            isCliEnvironment: $compilationContext->isCliEnvironment,
            forceCompilation: true
        );
        $this->cacheManager->clearHashCache($pathWithoutHash);

        return $this->collectAssetsAndCompile(
            $configuration,
            $newCompilationContext
        )->map(
            fn (FilePath $f) => new ManagerResultInfo($f, usedExistingFile: false)
        );
    }

    /**
     * @return Result<FilePath,Throwable>
     */
    private function collectAssetsAndCompile(
        Configuration $configuration,
        CompilationContext $compilationContext,
    ): Result {
        ProfilingUtility::start('Will compile assets');

        $outputFilePathWithoutHash = $this->outputFileService
            ->getPathWithoutHash($configuration);

        $currentState = new BuildState(
            $outputFilePathWithoutHash,
            $outputFilePathWithoutHash,
            []
        );

        $createDevelopmentSymlink = $this->getCreateDevelopmentSymlink(
            $configuration,
            $compilationContext
        );

        $buildSteps = $this->getBuildSteps($createDevelopmentSymlink);
        foreach ($buildSteps as $buildStep) {
            ProfilingUtility::start('Will process build step ' . get_class($buildStep));
            $currentStateResult = $buildStep->process($configuration, $currentState);
            ProfilingUtility::end('Did process build step ' . get_class($buildStep));
            if ($currentStateResult->isErr()) {
                return Result::err($currentStateResult->unwrapErr());
            }
            $currentState = $currentStateResult->unwrap();
        }

        ProfilingUtility::end('Did compile assets');

        return Result::ok($currentState->getFilePath());
    }

    /**
     * Return if the files should be compiled
     */
    public function willCompile(
        Configuration $configuration,
        CompilationContext $compilationContext,
    ): bool {
        // Check if compilation is force (e.g. because the file does not exist,
        // when invoked from TYPO3 backend or CLI context)
        if ($compilationContext->forceCompilation) {
            return true;
        }

        if ($configuration->isDevelopment) {
            return true;
        }

        if (!$compilationContext->isBackendUserLoggedIn) {
            // If no backend user is logged in, check if compiling is still allowed
            return $configuration->allowCompileWithoutLogin;
        } else {
            return false;
        }
    }

    private function getCreateDevelopmentSymlink(
        Configuration $configuration,
        CompilationContext $compilationContext,
    ): bool {
        $liveReloadEnabled = $configuration->liveReloadConfiguration
            ->isEnabled;
        $createSymlink = $configuration->createSymlink;
        if (!$createSymlink && !$liveReloadEnabled) {
            return false;
        }

        // If symlink creation or Live Reload is enabled check the current
        // callers permissions
        return $compilationContext->isCliEnvironment
            || $compilationContext->isBackendUserLoggedIn
            || $configuration->allowCompileWithoutLogin;
    }

    /**
     * @return BuildStepInterface<covariant Throwable>[]
     */
    private function getBuildSteps(bool $createDevelopmentSymlink): array
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

        if ($createDevelopmentSymlink) {
            // Create new symlink
            $buildSteps[] = new BuildStep\CreateNewSymlink($this->symlinkService);
        }

        return $buildSteps;
    }
}
