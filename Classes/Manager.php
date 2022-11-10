<?php
declare(strict_types=1);

namespace Cundd\Assetic;

use Assetic\Asset\AssetCollection;
use Cundd\Assetic\BuildStep\BuildStepInterface;
use Cundd\Assetic\Compiler\Compiler;
use Cundd\Assetic\Compiler\CompilerInterface;
use Cundd\Assetic\Configuration\ConfigurationProviderFactory;
use Cundd\Assetic\Configuration\ConfigurationProviderInterface;
use Cundd\Assetic\Service\CacheManager;
use Cundd\Assetic\Service\CacheManagerInterface;
use Cundd\Assetic\Service\OutputFileFinder;
use Cundd\Assetic\Service\OutputFileFinderInterface;
use Cundd\Assetic\Service\OutputFileHashService;
use Cundd\Assetic\Service\OutputFileService;
use Cundd\Assetic\Service\OutputFileServiceInterface;
use Cundd\Assetic\Service\SymlinkService;
use Cundd\Assetic\Service\SymlinkServiceInterface;
use Cundd\Assetic\Utility\GeneralUtility as AsseticGeneralUtility;
use Cundd\Assetic\ValueObject\BuildState;
use Cundd\Assetic\ValueObject\FilePath;
use Cundd\Assetic\ValueObject\PathWoHash;
use Cundd\Assetic\ValueObject\Result;
use LogicException;
use function file_exists;

/**
 * Assetic Manager
 */
class Manager implements ManagerInterface
{
    /**
     * Indicates if the assets will compile
     *
     * @var boolean
     */
    protected $willCompile = -1;

    /**
     * Indicates if experimental features are enabled
     *
     * @var bool
     */
    protected $experimental = -1;

    private CompilerInterface $compiler;

    private CacheManagerInterface $cacheManager;

    private ConfigurationProviderInterface $configurationProvider;

    private OutputFileHashService $outputFileHashService;

    private OutputFileServiceInterface $outputFileService;

    private SymlinkServiceInterface $symlinkService;

    private OutputFileFinderInterface $outputFileFinder;

    public function __construct(
        ConfigurationProviderFactory $configurationProviderFactory,
        CacheManagerInterface $cacheManager,
        OutputFileHashService $outputFileHashService,
        OutputFileServiceInterface $outputFileService,
        SymlinkServiceInterface $symlinkService,
        OutputFileFinderInterface $outputFileFinder
    ) {
        $this->configurationProvider = $configurationProviderFactory->build();
        $this->cacheManager = $cacheManager;
        $this->outputFileHashService = $outputFileHashService;
        $this->outputFileService = $outputFileService;
        $this->symlinkService = $symlinkService;
        $this->outputFileFinder = $outputFileFinder;
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
     * @return Result<FilePath>
     */
    private function collectAssetsAndCompile(): Result
    {
        $this->collectAssetsAndSetTarget();

        $outputFilePathWithoutHash = $this->outputFileService->getPathWoHash();
        $currentState = new BuildState($outputFilePathWithoutHash, $outputFilePathWithoutHash, []);

        $buildSteps = $this->getBuildSteps();
        foreach ($buildSteps as $buildStep) {
            $currentStateResult = $buildStep->process($currentState);
            if ($currentStateResult->isErr()) {
                return Result::err($currentStateResult->unwrapErr());
            }
            $currentState = $currentStateResult->unwrap();
        }

        $createDevelopmentSymlink = $this->configurationProvider->getCreateSymlink()
            && AsseticGeneralUtility::isBackendUser();

        if ($createDevelopmentSymlink) {
            return Result::ok($this->symlinkService->getSymlinkPath($currentState->getOutputFilePathWithoutHash()));
        } else {
            return Result::ok($currentState->getFilePath());
        }
    }

    /**
     * Return the Compiler instance
     *
     * @return CompilerInterface
     */
    public function getCompiler(): CompilerInterface
    {
        if (!isset($this->compiler)) {
            $this->compiler = new Compiler($this->configurationProvider, $this->getPluginLevelOptions());
        }

        return $this->compiler;
    }

    /**
     * Collect all the assets and add them to the Asset Manager
     *
     * @return AssetCollection
     * @throws LogicException if the Assetic classes could not be found
     */
    public function collectAssets(): AssetCollection
    {
        return $this->collectAssetsAndSetTarget();
    }

    /**
     * Collect the assets and set the target path
     *
     * @return AssetCollection
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
        $this->willCompile = true;

        return $this;
    }

    /**
     * Return if the files should be compiled
     *
     * @return boolean
     */
    public function willCompile(): bool
    {
        if ($this->willCompile === -1) {
            // If no backend user is logged in, check if it is allowed
            $isDevelopment = $this->configurationProvider->isDevelopment();
            if (!AsseticGeneralUtility::isBackendUser()) {
                $this->willCompile = $this->configurationProvider->isDevelopment()
                    || $this->configurationProvider->getAllowCompileWithoutLogin();
            } else {
                $this->willCompile = $isDevelopment;
            }

            AsseticGeneralUtility::say(
                'Backend user detected: ' . (AsseticGeneralUtility::isBackendUser() ? 'yes' : 'no')
            );
            AsseticGeneralUtility::say('Development mode: ' . ($isDevelopment ? 'on' : 'off'));
            AsseticGeneralUtility::say('Will compile: ' . ($this->willCompile ? 'yes' : 'no'));
        }

        return $this->willCompile;
    }

    public function getPathWOHash(): PathWoHash
    {
        return $this->outputFileService->getPathWoHash();
    }

    /**
     * Return the symlink URI
     *
     * @return string
     */
    public function getSymlinkUri(): string
    {
        return $this->symlinkService->getSymlinkPath($this->getPathWOHash())->getPublicUri();
    }

    /**
     * Return the "options" configuration from the TypoScript of the current page
     *
     * @return array
     */
    public function getPluginLevelOptions(): array
    {
        // Get the options
        $pluginLevelOptions = $this->configurationProvider->getOptions() ?? [];

        // Check for the development mode
        $pluginLevelOptions['debug'] = $this->configurationProvider->isDevelopment();

        return $pluginLevelOptions;
    }

    public function clearHashCache(): void
    {
        $this->cacheManager->clearHashCache($this->getPathWOHash());
    }

    /**
     * @return BuildStepInterface[]
     */
    private function getBuildSteps(): array
    {
        return [
            // Collect old compiled files to clean up
            new BuildStep\CollectFilesToCleanUp($this->outputFileFinder),

            // Remove old symlinks
            new BuildStep\RemoveOldSymlinks($this->symlinkService),

            // Compile
            new BuildStep\Compile($this->compiler, new ConfigurationProviderFactory()),

            // Clean up old files
            new BuildStep\CleanUpOldFiles(),

            // Build hashed file
            new BuildStep\AddHashToFileName($this->outputFileHashService),

            // Create new symlink
            new BuildStep\CreateNewSymlink($this->symlinkService),
        ];
    }
}
