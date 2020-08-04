<?php
declare(strict_types=1);

namespace Cundd\Assetic;

use Assetic\Asset\AssetCollection;
use Cundd\Assetic\Compiler\Compiler;
use Cundd\Assetic\Compiler\CompilerInterface;
use Cundd\Assetic\Configuration\ConfigurationProvider;
use Cundd\Assetic\Exception\OutputFileException;
use Cundd\Assetic\Exception\SymlinkException;
use Cundd\Assetic\Utility\ConfigurationUtility;
use Cundd\Assetic\Utility\GeneralUtility as AsseticGeneralUtility;
use Exception;
use LogicException;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Cache\Exception\NoSuchCacheException;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Assetic Manager
 */
class Manager implements ManagerInterface
{
    /**
     * Cache identifier for the hash
     */
    private const CACHE_IDENTIFIER_HASH = 'cundd_assetic_cache_identifier_hash';

    /**
     * Indicates if the assets will compile
     *
     * @var boolean
     */
    protected $willCompile = -1;

    /**
     * Compiler instance
     *
     * @var CompilerInterface
     */
    protected $compiler;

    /**
     * Cache manager
     *
     * @var CacheManager
     */
    protected $cacheManager;

    /**
     * @var ConfigurationProvider
     */
    protected $configurationProvider;

    /**
     * The name of the output file
     *
     * @var string
     */
    protected $outputFileName;

    /**
     * Previous hash
     *
     * @var string
     */
    protected $previousHash = '';

    /**
     * Defines if this instance is the owner of the symlink
     *
     * This defines if the instance is allowed to create a new symlink and was able to delete the old one
     *
     * @var bool
     */
    protected $isOwnerOfSymlink = false;

    /**
     * Indicates if experimental features are enabled
     *
     * @var bool
     */
    protected $experimental = -1;

    /**
     * Previously filtered asset files that will be removed
     *
     * @var array
     */
    protected $filesToRemove = [];

    public function __construct(ConfigurationProvider $configurationProvider)
    {
        $this->configurationProvider = $configurationProvider;
    }

    /**
     * Collect and compile assets and return the relative path to the compiled stylesheet
     *
     * @return string
     */
    public function collectAndCompile(): string
    {
        $renderedStylesheet = null;

        // Check if the assets should be compiled
        if ($this->willCompile()) {
            return $this->collectAssetsAndCompile();
        }

        $outputFileDir = $this->configurationProvider->getOutputFileDir();
        $renderedStylesheet = $outputFileDir . $this->getCurrentOutputFilename();

        /*
         * Check if the expected output file exists. If it doesn't, set
         * willCompile to TRUE and call the main routine again
         */
        $absolutePathToRenderedFile = $this->configurationProvider->getPublicPath() . $renderedStylesheet;
        if (!file_exists($absolutePathToRenderedFile)) {
            $this->forceCompile();

            return $this->collectAssetsAndCompile();
        }
        AsseticGeneralUtility::pd(
            $outputFileDir . $this->getCurrentOutputFilename(),
            $outputFileDir,
            $this->getCurrentOutputFilename()
        );

        return $renderedStylesheet;
    }

    /**
     * Return the Compiler instance
     *
     * @return CompilerInterface
     */
    public function getCompiler(): \Cundd\Assetic\Compiler\CompilerInterface
    {
        if (!$this->compiler) {
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
    public function collectAssets(): \Assetic\Asset\AssetCollection
    {
        return $this->collectAssetsAndSetTarget();
    }

    /**
     * Collect the assets and set the target path
     *
     * @return AssetCollection
     */
    protected function collectAssetsAndSetTarget()
    {
        $assetCollection = $this->getCompiler()->collectAssets();

        AsseticGeneralUtility::profile('Set output file ' . $this->getCurrentOutputFilenameWithoutHash());
        $assetCollection->setTargetPath($this->getCurrentOutputFilenameWithoutHash());

        return $assetCollection;
    }

    /**
     * Move the filtered temporary file to the path with the hash in the name
     *
     * @return string Returns the new file name
     */
    protected function moveTempFileToFileWithHash()
    {
        // $hashAlgorithm = 'crc32';
        // $hashAlgorithm = 'sha1';
        $hashAlgorithm = 'md5';

        $outputFilenameWithoutHash = $this->getCurrentOutputFilenameWithoutHash();
        $outputFileDir = ConfigurationUtility::getPathToWeb() . $this->configurationProvider->getOutputFileDir();
        $outputFileTempPath = $outputFileDir . $outputFilenameWithoutHash;

        // Create the file hash and store it in the cache
        AsseticGeneralUtility::profile('Will create file hash');

        $fileHash = hash_file($hashAlgorithm, $outputFileTempPath);
        AsseticGeneralUtility::profile('Did create file hash');
        $this->setCache(self::CACHE_IDENTIFIER_HASH . '_' . $outputFilenameWithoutHash, $fileHash);
        $finalFileName = $outputFilenameWithoutHash . '_' . $fileHash . '.css';

        $this->_setCurrentOutputFilename($finalFileName);
        $outputFileFinalPath = $outputFileDir . $finalFileName;

        $this->removePreviousFilteredAssetFiles();

        // Move the temp file to the new file
        AsseticGeneralUtility::profile('Will move compiled asset');

        clearstatcache(true, $outputFileFinalPath);
        if (is_link($outputFileFinalPath)) {
            if (!unlink($outputFileFinalPath)) {
                throw new OutputFileException(sprintf('Output file "%s" already exists', $outputFileFinalPath));
            }
        }
        if (!rename($outputFileTempPath, $outputFileFinalPath)) {
            $reason = $this->getReasonForWriteFailure($outputFileFinalPath);
            throw new OutputFileException(
                sprintf(
                    'Could not rename temporary output file. Source: "%s", destination: "%s" because %s',
                    $outputFileTempPath,
                    $outputFileFinalPath,
                    $reason
                )
            );
        }
        AsseticGeneralUtility::profile('Did move compiled asset');

        $this->createSymlinkToFinalPath($outputFileFinalPath);

        return $finalFileName;
    }

    /**
     * Force the recompilation
     *
     * @return void
     */
    public function forceCompile(): void
    {
        $this->willCompile = true;
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

    /**
     * Return the current output filename
     *
     * @return string
     */
    public function getOutputFilePath(): string
    {
        return $this->configurationProvider->getOutputFileDir() . $this->getCurrentOutputFilename();
    }

    /**
     * Return the current output filename without the hash
     *
     * If an output file name is set in the configuration use it, otherwise create it by combining the file names of the
     * assets.
     *
     * @return string
     */
    public function getCurrentOutputFilenameWithoutHash()
    {
        // Get the output name from the configuration
        if ($this->configurationProvider->getOutputFileName()) {
            return ConfigurationUtility::getDomainIdentifier() . $this->configurationProvider->getOutputFileName();
        }

        $outputFileNameParts = [];

        // Loop through all configured stylesheets
        $stylesheets = $this->configurationProvider->getStylesheetConfigurations();
        foreach ($stylesheets as $assetKey => $stylesheet) {
            // If the current value of $stylesheet is an array it's the detailed configuration of a stylesheet, not
            // the stylesheet path itself
            if (!is_array($stylesheet)) {
                $stylesheetFileName = basename($stylesheet);
                $stylesheetFileName = str_replace(['.', ' '], '', $stylesheetFileName);
                $outputFileNameParts[] = $stylesheetFileName;
            }
        }

        return ConfigurationUtility::getDomainIdentifier() . implode('_', $outputFileNameParts);
    }

    /**
     * Return the current output filename
     *
     * The current output filename may be changed if when the hash of the
     * filtered asset file is generated
     *
     * @return string
     */
    public function getCurrentOutputFilename(): string
    {
        if (!$this->outputFileName) {
            // Add a hash for caching
            $newHash = $this->getHash();
            $this->outputFileName = $this->getCurrentOutputFilenameWithoutHash();
            $this->outputFileName .= '_' . $newHash;
            $this->outputFileName .= '.css';
            AsseticGeneralUtility::pd($this->outputFileName);
        }
        AsseticGeneralUtility::pd($this->outputFileName);

        return $this->outputFileName;
    }

    /**
     * Set the current output filename
     *
     * @param string $outputFileName
     */
    protected function _setCurrentOutputFilename(string $outputFileName)
    {
        $this->outputFileName = $outputFileName;
    }

    /**
     * Return the hash for the current asset version
     *
     * @return string
     */
    protected function getHash(): string
    {
        $entry = $this->getPreviousHash();

        // If $entry is null, it hasn't been cached. Calculate the value and store it in the cache:
        if ($this->willCompile() || !$entry) {
            $entry = '';

            // Save value in cache
            $this->setCache(self::CACHE_IDENTIFIER_HASH . '_' . $this->getCurrentOutputFilenameWithoutHash(), $entry);
        }
        AsseticGeneralUtility::pd($entry);

        return $entry;
    }

    /**
     * Return the hash from the cache, or an empty string if it is not set
     *
     * @return string
     */
    protected function getPreviousHash(): string
    {
        if (!$this->previousHash) {
            $suffix = '.css';
            $filePath = $this->configurationProvider->getOutputFileDir() . $this->getCurrentOutputFilenameWithoutHash();

            $previousHash = (string)$this->getCache(
                self::CACHE_IDENTIFIER_HASH . '_' . $this->getCurrentOutputFilenameWithoutHash()
            );
            $previousHashFilePath = $filePath . '_' . $previousHash . $suffix;

            if (!$previousHash || !file_exists($previousHashFilePath)) {
                $matchingFiles = $this->findPreviousFilteredAssetFiles($filePath, $suffix);
                if (!$matchingFiles) {
                    return '';
                }
                $lastMatchingFile = end($matchingFiles);
                $previousHash = substr($lastMatchingFile, strlen($filePath) + 1, (-1 * strlen($suffix)));
            }

            $this->previousHash = $previousHash;
        }

        return $this->previousHash;
    }


    // MWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWM
    // CACHING AND SYMLINK
    // MWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWM
    /**
     * Collect the previous filtered Asset files and remove the symlink
     */
    public function collectPreviousFilteredAssetFilesAndRemoveSymlink()
    {
        $this->removeSymlink();
        $this->collectPreviousFilteredAssetFiles();
    }

    /**
     * Collect the previous filtered Asset files
     */
    public function collectPreviousFilteredAssetFiles()
    {
        $suffix = '.css';
        $outputFileDir = ConfigurationUtility::getPathToWeb() . $this->configurationProvider->getOutputFileDir();
        $filePath = $outputFileDir . $this->getCurrentOutputFilenameWithoutHash();
        $this->filesToRemove = $this->findPreviousFilteredAssetFiles($filePath, $suffix);
    }

    /**
     * Create the symlink to the given final path
     *
     * @param string $fileFinalPath
     */
    public function createSymlinkToFinalPath($fileFinalPath)
    {
        if (!$this->configurationProvider->getCreateSymlink()) {
            return;
        }
        $symlinkPath = $this->getSymlinkPath();
        if ($fileFinalPath !== $symlinkPath) {
            clearstatcache(true, $symlinkPath);
            if ($this->isOwnerOfSymlink || !is_link($symlinkPath)) {
                if (!is_link($symlinkPath) && !symlink($fileFinalPath, $symlinkPath)) {
                    throw new SymlinkException(
                        sprintf(
                            'Could not create the symlink "%s" because %s',
                            $symlinkPath,
                            $this->getReasonForWriteFailure($symlinkPath)
                        ),
                        1456396454
                    );
                }
            } else {
                throw new SymlinkException(
                    sprintf(
                        'Could not create the symlink because the file "%s" already exists and the manager is not the symlink\'s owner',
                        $symlinkPath
                    )
                );
            }
        }
    }

    /**
     * Remove the symlink
     */
    public function removeSymlink()
    {
        if (!$this->configurationProvider->getCreateSymlink()) {
            return;
        }
        // Unlink the symlink
        $symlinkPath = $this->getSymlinkPath();
        if (is_link($symlinkPath)) {
            if (unlink($symlinkPath)) {
                $this->isOwnerOfSymlink = true;
            } else {
                $this->isOwnerOfSymlink = false;
                throw new SymlinkException(
                    sprintf('Could not acquire ownership of symlink "%s"', $symlinkPath)
                );
            }
        } elseif (!file_exists($symlinkPath)) {
            $this->isOwnerOfSymlink = true;
        } else {
            throw new SymlinkException(
                sprintf('Could not acquire ownership of symlink "%s" because it exists but is no link', $symlinkPath)
            );
        }
    }

    /**
     * Return the symlink URI
     *
     * @return string
     */
    public function getSymlinkUri(): string
    {
        return $this->configurationProvider->getOutputFileDir()
            . '_debug_'
            . $this->getCurrentOutputFilenameWithoutHash()
            . '.css';
    }

    /**
     * Return the symlink path
     *
     * @return string
     */
    public function getSymlinkPath(): string
    {
        return ConfigurationUtility::getPathToWeb() . $this->getSymlinkUri();
    }

    /**
     * Remove the previous filtered Asset files
     *
     * @return boolean    Returns TRUE if the file was removed, otherwise FALSE
     */
    public function removePreviousFilteredAssetFiles(): bool
    {
        $success = true;
        $matchingFiles = $this->filesToRemove;
        if (!$matchingFiles) {
            return false;
        }
        foreach ($matchingFiles as $oldFilteredAssetFile) {
            $success *= @unlink($oldFilteredAssetFile);
        }

        return (bool)$success;
    }

    /**
     * Return an array of previously filtered Asset files
     *
     * @param string $filePath
     * @param string $suffix
     * @return array
     */
    protected function findPreviousFilteredAssetFiles(string $filePath, string $suffix = '.css')
    {
        AsseticGeneralUtility::profile('Will call glob for previous filtered Asset files');
        $matchingFiles = glob($filePath . '_' . '*' . $suffix);
        AsseticGeneralUtility::profile('Did call glob for previous filtered Asset files');

        // Glob will not return invalid symlinks
        if (!$matchingFiles) {
            return [];
        }

        AsseticGeneralUtility::profile('Will sort previous filtered Asset files by modification time');
        // Sort by mtime
        usort(
            $matchingFiles,
            function ($a, $b) {
                return filemtime($a) - filemtime($b);
            }
        );
        AsseticGeneralUtility::profile('Did sort previous filtered Asset files by modification time');

        return $matchingFiles;
    }


    // MWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWM
    // HELPERS
    // MWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWM
    /**
     * Return the "options" configuration from the TypoScript of the current page
     *
     * @return array
     */
    public function getPluginLevelOptions()
    {
        // Get the options
        $pluginLevelOptions = $this->configurationProvider->getOptions()
            ?? ['output' => $this->getCurrentOutputFilename()];

        // Check for the development mode
        $pluginLevelOptions['debug'] = $this->configurationProvider->isDevelopment();

        return $pluginLevelOptions;
    }

    /**
     * Return if experimental features are enabled
     *
     * @return boolean
     * @deprecated
     */
    public function getExperimental(): bool
    {
        if ($this->experimental === -1) {
            $this->experimental = $this->configurationProvider->getLiveReloadConfiguration()->getAddJavascript();
            if ($this->configurationProvider->getEnableExperimentalFeatures()) {
                $this->experimental = true;
            }
        }

        return $this->experimental;
    }

    /**
     * Try to detect the reason for the write failure
     *
     * @param string $path
     * @return string
     */
    private function getReasonForWriteFailure($path)
    {
        clearstatcache(true, $path);
        if (file_exists($path)) {
            $reason = 'the file exists';
        } elseif (is_link($path)) {
            $reason = 'it is a link';
        } elseif (!is_writable(dirname($path))) {
            $reason = 'the directory is not writable';
        } elseif (!is_writable($path)) {
            $reason = 'the file path is not writable';
        } else {
            $reason = 'of an unknown reason';
        }

        return $reason;
    }


    // MWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWM
    // READING AND WRITING THE CACHE
    // MWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWM
    /**
     * Return the value for the given identifier in the cache
     *
     * @param string $identifier Identifier key
     * @return mixed
     */
    protected function getCache(string $identifier)
    {
        $identifier = sha1(ConfigurationUtility::getDomainIdentifier() . '-' . $identifier);
        AsseticGeneralUtility::pd(ConfigurationUtility::getDomainIdentifier() . '-' . $identifier);

        if (is_callable('apc_fetch')) {
            /** @noinspection PhpComposerExtensionStubsInspection */
            return apc_fetch($identifier);
        }

        try {
            $cacheInstance = $this->getCacheManager()->getCache('assetic_cache');
        } catch (NoSuchCacheException $e) {
            return null;
        }
        if (!$cacheInstance) {
            return null;
        }

        return $cacheInstance->get($identifier);
    }

    /**
     * Stores the value for the given identifier in the cache
     *
     * @param string $identifier Identifier key
     * @param mixed  $value      Value to store
     */
    protected function setCache(string $identifier, $value)
    {
        $identifier = sha1(ConfigurationUtility::getDomainIdentifier() . '-' . $identifier);
        AsseticGeneralUtility::pd(ConfigurationUtility::getDomainIdentifier() . '-' . $identifier);

        if (is_callable('apc_store')) {
            /** @noinspection PhpComposerExtensionStubsInspection */
            apc_store($identifier, $value);
        } else {
            $tags = [];
            $lifetime = 60 * 60 * 24; // * 365 * 10;

            try {
                $cacheInstance = $this->getCacheManager()->getCache('assetic_cache');
            } catch (NoSuchCacheException $e) {
                return;
            }
            if (!$cacheInstance) {
                return;
            }
            $cacheInstance->set($identifier, $value, $tags, $lifetime);
        }
    }

    /**
     * Return the Cache Manager
     *
     * @return CacheManager
     */
    protected function getCacheManager()
    {
        if (!$this->cacheManager) {
            $this->cacheManager = GeneralUtility::makeInstance(CacheManager::class);
        }

        return $this->cacheManager;
    }

    /**
     * Remove the cached hash
     *
     * @return void
     */
    public function clearHashCache(): void
    {
        $this->setCache(self::CACHE_IDENTIFIER_HASH . '_' . $this->getCurrentOutputFilenameWithoutHash(), '');
    }

    /**
     * @return string
     * @throws OutputFileException
     */
    private function collectAssetsAndCompile()
    {
        $this->collectAssetsAndSetTarget();
        $this->collectPreviousFilteredAssetFilesAndRemoveSymlink();
        try {
            if ($this->compiler->compile()) {
                $renderedStylesheet = $this->configurationProvider->getOutputFileDir(
                    ) . $this->moveTempFileToFileWithHash();
                AsseticGeneralUtility::pd('$renderedStylesheet', $renderedStylesheet);

                return $this->configurationProvider->getCreateSymlink() && AsseticGeneralUtility::isBackendUser()
                    ? $this->getSymlinkUri()
                    : $renderedStylesheet;
            }
        } catch (Exception $exception) {
            throw new OutputFileException('No output file compiled', 1555431572, $exception);
        }

        // TODO: Find a way to recover the browser after a compile run failed
        // $isStrict = isset($this->configuration['strict']) && $this->configuration['strict'];
        // if ($this->getExperimental() && AsseticGeneralUtility::isBackendUser() && !$isStrict) {
        //     return $this->getSymlinkUri();
        // }

        throw new OutputFileException('No output file compiled');
    }
}
