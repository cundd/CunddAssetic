<?php

declare(strict_types=1);

namespace Cundd\Assetic;

use Assetic\Asset\AssetCollection;
use Cundd\Assetic\BuildStep\BuildStepInterface;
use Cundd\Assetic\Compiler\CompilerFactory;
use Cundd\Assetic\Compiler\CompilerInterface;
use Cundd\Assetic\Service\CacheManagerInterface;
use Cundd\Assetic\Service\OutputFileFinderInterface;
use Cundd\Assetic\Service\OutputFileHashService;
use Cundd\Assetic\Service\OutputFileServiceInterface;
use Cundd\Assetic\Service\SymlinkServiceInterface;
use Cundd\Assetic\Utility\ProfilingUtility;
use Cundd\Assetic\ValueObject\BuildState;
use Cundd\Assetic\ValueObject\CompilationContext;
use Cundd\Assetic\ValueObject\FilePath;
use Cundd\Assetic\ValueObject\PathWithoutHash;
use Cundd\Assetic\ValueObject\Result;
use LogicException;
use Throwable;

use function file_exists;

/**
 * Assetic Manager
 */
class Manager implements ManagerInterface
{
    /**
     * Indicates if the assets will compile
     */
    private bool $forceCompilation = false;

    private readonly CompilerInterface $compiler;

    public function __construct(
        CompilerFactory $compilerFactory,
        private readonly CacheManagerInterface $cacheManager,
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
            );
        }

        $pathWithoutHash = $this->getPathWithoutHash($configuration);
        $expectedPath = $this->outputFileService
            ->getExpectedPathWithHash($configuration, $pathWithoutHash);
        if ($expectedPath && file_exists($expectedPath->getAbsoluteUri())) {
            return Result::ok($expectedPath);
        }

        // If expected output file does not exist clear the internal cache,
        // set `willCompile` to TRUE and call the main routine again
        $this->forceCompile();
        $this->cacheManager->clearHashCache($pathWithoutHash);

        return $this->collectAssetsAndCompile(
            $configuration,
            $compilationContext
        );
    }

    /**
     * @return Result<FilePath,Throwable>
     */
    private function collectAssetsAndCompile(
        Configuration $configuration,
        CompilationContext $compilationContext,
    ): Result {
        $this->collectAssetsAndSetTarget($configuration);

        $outputFilePathWithoutHash = $this->outputFileService->getPathWithoutHash($configuration);
        $currentState = new BuildState(
            $outputFilePathWithoutHash,
            $outputFilePathWithoutHash,
            []
        );

        $buildSteps = $this->getBuildSteps($this->getCreateDevelopmentSymlink(
            $configuration,
            $compilationContext
        ));
        foreach ($buildSteps as $buildStep) {
            $currentStateResult = $buildStep->process($configuration, $currentState);
            if ($currentStateResult->isErr()) {
                return Result::err($currentStateResult->unwrapErr());
            }
            $currentState = $currentStateResult->unwrap();
        }

        return Result::ok($currentState->getFilePath());
    }

    /**
     * Return the Compiler instance
     */
    public function getCompiler(): CompilerInterface
    {
        return $this->compiler;
    }

    /**
     * Collect all the assets and add them to the Asset Manager
     *
     * @throws LogicException if the Assetic classes could not be found
     */
    public function collectAssets(Configuration $configuration): AssetCollection
    {
        return $this->collectAssetsAndSetTarget($configuration);
    }

    /**
     * Collect the assets and set the target path
     */
    protected function collectAssetsAndSetTarget(
        Configuration $configuration,
    ): AssetCollection {
        $assetCollection = $this->getCompiler()->collectAssets($configuration);

        $pathWithoutHashFileName = $this->getPathWithoutHash($configuration)
            ->getFileName();
        ProfilingUtility::profile('Set output file ' . $pathWithoutHashFileName);
        $assetCollection->setTargetPath($pathWithoutHashFileName);

        return $assetCollection;
    }

    public function forceCompile(): self
    {
        $this->forceCompilation = true;

        return $this;
    }

    /**
     * Return if the files should be compiled
     */
    public function willCompile(
        Configuration $configuration,
        CompilationContext $compilationContext,
    ): bool {
        if ($this->forceCompilation) {
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

    private function getPathWithoutHash(Configuration $configuration): PathWithoutHash
    {
        return $this->outputFileService->getPathWithoutHash($configuration);
    }

    /**
     * Return the symlink URI
     */
    public function getSymlinkUri(Configuration $configuration): string
    {
        return $this->symlinkService
            ->getSymlinkPath(
                $configuration,
                $this->getPathWithoutHash($configuration)
            )
            ->getPublicUri();
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
