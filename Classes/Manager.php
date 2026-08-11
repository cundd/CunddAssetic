<?php

declare(strict_types=1);

namespace Cundd\Assetic;

use Cundd\Assetic\BuildSystem\ExecutorInterface;
use Cundd\Assetic\Output\CacheManagerInterface;
use Cundd\Assetic\Output\OutputFileServiceInterface;
use Cundd\Assetic\Output\PreviousOutputFileServiceInterface;
use Cundd\Assetic\ValueObject\BuildState;
use Cundd\Assetic\ValueObject\CompilationContext;
use Cundd\Assetic\ValueObject\FilePath;
use Cundd\Assetic\ValueObject\ManagerResultInfo;
use Cundd\Assetic\ValueObject\Result;
use Cundd\Assetic\ValueObject\SymlinkFilePath;
use Throwable;

use function file_exists;

final class Manager implements ManagerInterface
{
    public function __construct(
        private readonly CacheManagerInterface $cacheManager,
        private readonly OutputFileServiceInterface $outputFileService,
        private readonly PreviousOutputFileServiceInterface $previousOutputFileService,
        private readonly ExecutorInterface $executor,
    ) {
    }

    public function collectAndCompile(
        Configuration $configuration,
        CompilationContext $compilationContext,
    ): Result {
        // Check if the assets should be compiled
        if ($this->shouldCompile($configuration, $compilationContext)) {
            return $this->build(
                $configuration,
                $compilationContext
            )->map(
                fn (FilePath $f) => new ManagerResultInfo($f, usedExistingFile: false)
            );
        }

        // Check if the cached file exists
        $pathWithoutHash = $this->outputFileService
            ->getPathWithoutHash($configuration);
        $expectedPath = $this->previousOutputFileService
            ->getPreviousPathWithHash($configuration, $pathWithoutHash);
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
        $this->cacheManager->clearFinalFilePath($pathWithoutHash);

        return $this->build(
            $configuration,
            $newCompilationContext
        )->map(
            fn (FilePath $f) => new ManagerResultInfo($f, usedExistingFile: false)
        );
    }

    /**
     * @return Result<FilePath,Throwable>
     */
    private function build(
        Configuration $configuration,
        CompilationContext $compilationContext,
    ): Result {
        $createDevelopmentSymlink = $this->getCreateDevelopmentSymlink(
            $configuration,
            $compilationContext
        );

        $builderConfiguration = $configuration->withCreateSymlink(
            $createDevelopmentSymlink
        );

        $currentStateResult = $this->executor->build($configuration);

        if ($currentStateResult->isOk()) {
            /** @var BuildState $currentState */
            $currentState = $currentStateResult->unwrap();
            $outputFile = $currentState->getFilePath();

            // Cache the latest compiled file path
            assert(!$outputFile->isSymlink() || $outputFile instanceof SymlinkFilePath);
            $pathToCache = $outputFile instanceof SymlinkFilePath
                ? $outputFile->readlink()
                : $outputFile;

            $this->cacheManager->setFinalFilePath(
                $currentState->getOutputFilePathWithoutHash(),
                $pathToCache
            );

            return Result::ok($currentState->getFilePath());
        } else {
            return Result::err($currentStateResult->unwrapErr());
        }
    }

    /**
     * Return if the files should be compiled
     */
    private function shouldCompile(
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

        return $compilationContext->shouldLoadLiveReload($configuration);
    }

    private function getCreateDevelopmentSymlink(
        Configuration $configuration,
        CompilationContext $compilationContext,
    ): bool {
        // `shouldLoadLiveReload()` also checks permissions
        if ($compilationContext->shouldLoadLiveReload($configuration)) {
            return true;
        }

        $createSymlink = $configuration->createSymlink;
        if (!$createSymlink) {
            return false;
        }

        // If symlink creation is enabled check the current callers permissions
        return $compilationContext->hasAccessToDevelopmentFeatures($configuration);
    }
}
