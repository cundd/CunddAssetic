<?php

declare(strict_types=1);

namespace Cundd\Assetic;

use Assetic\Asset\AssetCollection;
use Cundd\Assetic\BuildStep\BuildStepInterface;
use Cundd\Assetic\Compiler\CompilerFactory;
use Cundd\Assetic\Compiler\CompilerInterface;
use Cundd\Assetic\Configuration\ConfigurationProviderFactory;
use Cundd\Assetic\Configuration\ConfigurationProviderInterface;
use Cundd\Assetic\Service\CacheManagerInterface;
use Cundd\Assetic\Service\OutputFileFinderInterface;
use Cundd\Assetic\Service\OutputFileHashService;
use Cundd\Assetic\Service\OutputFileServiceInterface;
use Cundd\Assetic\Service\SymlinkServiceInterface;
use Cundd\Assetic\Utility\BackendUserUtility;
use Cundd\Assetic\Utility\GeneralUtility as AsseticGeneralUtility;
use Cundd\Assetic\ValueObject\BuildState;
use Cundd\Assetic\ValueObject\FilePath;
use Cundd\Assetic\ValueObject\PathWoHash;
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

    private CompilerInterface $compiler;

    private readonly ConfigurationProviderInterface $configurationProvider;

    public function __construct(
        ConfigurationProviderFactory $configurationProviderFactory,
        CompilerFactory $compilerFactory,
        private readonly CacheManagerInterface $cacheManager,
        private readonly OutputFileHashService $outputFileHashService,
        private readonly OutputFileServiceInterface $outputFileService,
        private readonly SymlinkServiceInterface $symlinkService,
        private readonly OutputFileFinderInterface $outputFileFinder,
    ) {
        $this->compiler = $compilerFactory->build();
        $this->configurationProvider = $configurationProviderFactory->build();
    }

    public function collectAndCompile(): Result
    {
        // Check if the assets should be compiled
        if ($this->willCompile()) {
            return $this->collectAssetsAndCompile();
        }

        $pathWOHash = $this->getPathWOHash();
        $expectedPath = $this->outputFileService->getExpectedPathWithHash($pathWOHash);
        if ($expectedPath && file_exists($expectedPath->getAbsoluteUri())) {
            return Result::ok($expectedPath);
        }

        // If expected output file does not exist clear the internal cache, set `willCompile` to TRUE
        // and call the main routine again
        $this->forceCompile();
        $this->cacheManager->clearHashCache($pathWOHash);

        return $this->collectAssetsAndCompile();
    }

    /**
     * @return Result<FilePath,Throwable>
     */
    private function collectAssetsAndCompile(): Result
    {
        $this->collectAssetsAndSetTarget();

        $outputFilePathWithoutHash = $this->outputFileService->getPathWoHash();
        $currentState = new BuildState($outputFilePathWithoutHash, $outputFilePathWithoutHash, []);

        $buildSteps = $this->getBuildSteps($this->getCreateDevelopmentSymlink());
        foreach ($buildSteps as $buildStep) {
            $currentStateResult = $buildStep->process($currentState);
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
    public function collectAssets(): AssetCollection
    {
        return $this->collectAssetsAndSetTarget();
    }

    /**
     * Collect the assets and set the target path
     */
    protected function collectAssetsAndSetTarget(): AssetCollection
    {
        $assetCollection = $this->getCompiler()->collectAssets();

        AsseticGeneralUtility::profile('Set output file ' . $this->getPathWOHash()->getFileName());
        $assetCollection->setTargetPath($this->getPathWOHash()->getFileName());

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
    public function willCompile(): bool
    {
        if ($this->forceCompilation) {
            return true;
        }

        $isDevelopment = $this->configurationProvider->isDevelopment();
        if ($isDevelopment) {
            return true;
        }

        $isUserLoggedIn = BackendUserUtility::isUserLoggedIn();

        AsseticGeneralUtility::say(
            'Backend user detected: ' . ($isUserLoggedIn ? 'yes' : 'no')
        );
        AsseticGeneralUtility::say('Development mode: off');
        if (!$isUserLoggedIn) {
            // If no backend user is logged in, check if compiling is still allowed
            return $this->configurationProvider->getAllowCompileWithoutLogin();
        } else {
            return false;
        }
    }

    public function getPathWOHash(): PathWoHash
    {
        return $this->outputFileService->getPathWoHash();
    }

    /**
     * Return the symlink URI
     */
    public function getSymlinkUri(): string
    {
        return $this->symlinkService->getSymlinkPath($this->getPathWOHash())->getPublicUri();
    }

    private function getCreateDevelopmentSymlink(): bool
    {
        $addLivereloadJavaScript = $this->configurationProvider
            ->getLiveReloadConfiguration()
            ->getAddJavascript();
        $createSymlink = $this->configurationProvider->getCreateSymlink();
        if (!$createSymlink && !$addLivereloadJavaScript) {
            return false;
        }

        return 'cli' === php_sapi_name()
            || BackendUserUtility::isUserLoggedIn()
            || $this->configurationProvider->getAllowCompileWithoutLogin();
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
