<?php
namespace Cundd\Assetic;

/*
 * Copyright (C) 2012 Daniel Corn
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 */

use Cundd\Assetic\Compiler\Compiler;
use Cundd\Assetic\Compiler\CompilerInterface;
use Cundd\Assetic\Utility\ConfigurationUtility;
use Cundd\Assetic\Utility\GeneralUtility as AsseticGeneralUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Assetic Manager
 *
 * @package Cundd_Assetic
 */
class Manager implements ManagerInterface
{
    /**
     * Cache identifier for the hash
     */
    const CACHE_IDENTIFIER_HASH = 'cundd_assetic_cache_identifier_hash';

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
     * @var \TYPO3\CMS\Core\Cache\CacheManager
     */
    protected $cacheManager;

    /**
     * @var array
     */
    protected $configuration;

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
    protected $filesToRemove = array();

    function __construct($configuration)
    {
        $this->configuration = $configuration;
    }

    /**
     * Collects and compiles assets and returns the code to include compiled stylesheets
     *
     * @return string
     */
    public function collectAndCompile()
    {
        AsseticGeneralUtility::profile('Cundd Assetic plugin begin');
        $renderedStylesheet = null;

        // Check if the assets should be compiled
        if ($this->willCompile()) {
            $this->collectAssetsAndSetTarget();
            if ($this->compiler->compile()) {
                $renderedStylesheet = ConfigurationUtility::getOutputFileDir() . $this->moveTempFileToFileWithHash();
                AsseticGeneralUtility::pd('$renderedStylesheet', $renderedStylesheet);
            }
        } else {
            $renderedStylesheet = ConfigurationUtility::getOutputFileDir() . $this->getCurrentOutputFilename();

            /*
             * Check if the expected output file exists. If it doesn't, set
             * willCompile to TRUE and call the main routine again
             */
            $absolutePathToRenderedFile = ConfigurationUtility::getPathToWeb() . $renderedStylesheet;
            if (!file_exists($absolutePathToRenderedFile)) {
                $this->forceCompile();
                return $this->collectAndCompile();
            }
            AsseticGeneralUtility::pd(
                ConfigurationUtility::getOutputFileDir() . $this->getCurrentOutputFilename(),
                ConfigurationUtility::getOutputFileDir(), $this->getCurrentOutputFilename()
            );
        }

        if ($this->getExperimental() && AsseticGeneralUtility::isBackendUser()) {
            $renderedStylesheet = $this->getSymlinkUri();
        }

        $content = '';
        $content .= '<link rel="stylesheet" type="text/css" href="' . $renderedStylesheet . '" media="all">';
        $content .= $this->getLiveReloadCode();

        AsseticGeneralUtility::profile('Cundd Assetic plugin end');
        return $content;
    }

    /**
     * Returns the Compiler instance
     *
     * @return CompilerInterface
     */
    public function getCompiler()
    {
        if (!$this->compiler) {
            $this->compiler = new Compiler($this->configuration);
            $this->compiler->setPluginLevelOptions($this->getPluginLevelOptions());
        }
        return $this->compiler;
    }

    /**
     * Collects the files and tells assetic to compile the files
     *
     * @throws \Exception if an exception is thrown during rendering
     * @return bool Returns if the files have been compiled successfully
     */
    public function compile()
    {
        return $this->getCompiler()->compile();
    }

    /**
     * Collects all the assets and adds them to the asset manager
     *
     * @throws \LogicException if the assetic classes could not be found
     * @return \Assetic\Asset\AssetCollection
     */
    public function collectAssets()
    {
        return $this->collectAssetsAndSetTarget();
    }

    /**
     * Collect the assets and set the target path
     *
     * @return \Assetic\Asset\AssetCollection
     */
    protected function collectAssetsAndSetTarget()
    {
        $assetCollection = $this->getCompiler()->collectAssets();

        AsseticGeneralUtility::profile('Set output file ' . $this->getCurrentOutputFilenameWithoutHash());
        $assetCollection->setTargetPath($this->getCurrentOutputFilenameWithoutHash());
        return $assetCollection;
    }

    /**
     * Moves the filtered temporary file to the path with the hash in the name
     *
     * @return string Returns the new file name
     */
    protected function moveTempFileToFileWithHash()
    {
        // $hashAlgorithm = 'crc32';
        // $hashAlgorithm = 'sha1';
        $hashAlgorithm = 'md5';

        $outputFilenameWithoutHash = $this->getCurrentOutputFilenameWithoutHash();
        $outputFileDir = ConfigurationUtility::getPathToWeb() . ConfigurationUtility::getOutputFileDir();
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
        rename($outputFileTempPath, $outputFileFinalPath);
        AsseticGeneralUtility::profile('Did move compiled asset');

        $this->createSymlinkToFinalPath($outputFileFinalPath);

        return $finalFileName;
    }

    /**
     * Returns the code for "live reload"
     *
     * @return string
     */
    protected function getLiveReloadCode()
    {
        if (!$this->getExperimental() || !AsseticGeneralUtility::isBackendUser()) {
            return '';
        }

        $port = 35729;
        if (isset($this->configuration['livereload.']) && isset($this->configuration['livereload.']['port'])) {
            $port = intval($this->configuration['livereload.']['port']);
        }

        $resource = 'EXT:assetic/Resources/Public/Library/livereload.js';
        $resource = '/' . str_replace(PATH_site, '', GeneralUtility::getFileAbsFileName($resource));
        $javaScriptCodeTemplate = "<script type=\"text/javascript\">
	(function () {
		var scriptElement = document.createElement('script');
		scriptElement.src = '%s' + '?host=' + location.host + '&port=%d';
		document.getElementsByTagName('head')[0].appendChild(scriptElement);
	})();
</script>";
        return sprintf($javaScriptCodeTemplate, $resource, $port);
    }

    /**
     * Force the recompilation
     *
     * @return void
     */
    public function forceCompile()
    {
        $this->willCompile = true;
    }

    /**
     * Returns if the files should be compiled
     *
     * @return boolean
     */
    public function willCompile()
    {
        if ($this->willCompile === -1) {
            // If no backend user is logged in, check if it is allowed
            if (!AsseticGeneralUtility::isBackendUser()) {
                $this->willCompile = (bool)($this->isDevelopment() * intval($this->configuration['allow_compile_without_login']));
            } else {
                $this->willCompile = $this->isDevelopment();
            }

            AsseticGeneralUtility::say('Backend user detected: ' . (AsseticGeneralUtility::isBackendUser() ? 'yes' : 'no'));
            AsseticGeneralUtility::say('Development mode: ' . ($this->isDevelopment() ? 'on' : 'off'));
            AsseticGeneralUtility::say('Will compile: ' . ($this->willCompile ? 'yes' : 'no'));

        }
        return $this->willCompile;
    }

    /**
     * Returns the configuration
     *
     * @return array
     */
    public function getConfiguration()
    {
        return $this->configuration;
    }

    /**
     * Returns the current output filename
     *
     * @return string
     */
    public function getOutputFilePath()
    {
        return ConfigurationUtility::getOutputFileDir() . $this->getCurrentOutputFilename();
    }

    /**
     * Returns the current output filename without the hash
     *
     * @return string
     */
    public function getCurrentOutputFilenameWithoutHash()
    {
        $outputFileName = '';

        /*
         * If an output file name is set in the configuration use it, otherwise
         * create it by combining the file names of the assets.
         */
        // Get the output name from the configuration
        if (isset($this->configuration['output'])) {
            $outputFileName = $this->configuration['output'];
        } else {
            // Loop through all configured stylesheets
            $stylesheets = $this->configuration['stylesheets.'];
            foreach ($stylesheets as $assetKey => $stylesheet) {
                if (!is_array($stylesheet)) {
                    $stylesheetFileName = basename($stylesheet);
                    $stylesheetFileName = str_replace(array('.', ' '), '', $stylesheetFileName);
                    $outputFileName .= $stylesheetFileName . '_';
                }
            }
        }
        return ConfigurationUtility::getDomainIdentifier() . $outputFileName;
    }

    /**
     * Returns the current output filename
     *
     * The current output filename may be changed if when the hash of the
     * filtered asset file is generated
     *
     * @return string
     */
    public function getCurrentOutputFilename()
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
     * Sets the current output filename
     *
     * @param string $outputFileName
     * @return string
     */
    protected function _setCurrentOutputFilename($outputFileName)
    {
        $this->outputFileName = $outputFileName;
    }


    /**
     * Returns the hash for the current asset version
     *
     * @return string
     */
    protected function getHash()
    {
        $entry = $this->getPreviousHash();

        // If $entry is null, it hasn't been cached. Calculate the value and store it in the cache:
        if ($this->willCompile() || !$entry) {
            $entry = '';#time();

            // Save value in cache
            $this->setCache(self::CACHE_IDENTIFIER_HASH . '_' . $this->getCurrentOutputFilenameWithoutHash(), $entry);
        }
        AsseticGeneralUtility::pd($entry);
        return $entry;
    }

    /**
     * Returns the hash from the cache, or an empty string if it is not set
     *
     * @return string
     */
    protected function getPreviousHash()
    {
        if (!$this->previousHash) {
            $suffix = '.css';
            $filePath = ConfigurationUtility::getOutputFileDir() . $this->getCurrentOutputFilenameWithoutHash();

            $previousHash = '' . $this->getCache(self::CACHE_IDENTIFIER_HASH . '_' . $this->getCurrentOutputFilenameWithoutHash());
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
        $outputFileDir = ConfigurationUtility::getPathToWeb() . ConfigurationUtility::getOutputFileDir();
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
        if (!$this->getExperimental()) {
            return;
        }
        $symlinkPath = $this->getSymlinkPath();
        if ($fileFinalPath !== $symlinkPath) {
            symlink($fileFinalPath, $symlinkPath);
        }
    }

    /**
     * Remove the symlink
     */
    public function removeSymlink()
    {
        if (!$this->getExperimental()) {
            return;
        }
        // Unlink the symlink
        $symlinkPath = $this->getSymlinkPath();
        if (file_exists($symlinkPath) && is_link($symlinkPath)) {
            unlink($symlinkPath);
        }
    }

    /**
     * Returns the symlink URI
     *
     * @return string
     */
    public function getSymlinkUri()
    {
        return ConfigurationUtility::getOutputFileDir() . '_debug_' . $this->getCurrentOutputFilenameWithoutHash() . '.css';
    }

    /**
     * Returns the symlink path
     *
     * @return string
     */
    public function getSymlinkPath()
    {
        return ConfigurationUtility::getPathToWeb() . $this->getSymlinkUri();
    }

    /**
     * Remove the previous filtered Asset files
     *
     * @return boolean    Returns TRUE if the file was removed, otherwise FALSE
     */
    public function removePreviousFilteredAssetFiles()
    {
        $success = true;
        $matchingFiles = $this->filesToRemove;
        if (!$matchingFiles) {
            return '';
        }
        foreach ($matchingFiles as $oldFilteredAssetFile) {
            $success *= unlink($oldFilteredAssetFile);
        }
        return $success;
    }

    /**
     * Returns an array of previously filtered Asset files
     *
     * @param string $filePath
     * @param string $suffix
     * @return array
     */
    protected function findPreviousFilteredAssetFiles($filePath, $suffix = '.css')
    {
        AsseticGeneralUtility::profile('Will call glob');
        $matchingFiles = glob($filePath . '_' . '*' . $suffix);
        AsseticGeneralUtility::profile('Did call glob');

        AsseticGeneralUtility::pd('GLOB', $filePath);

        if (!$matchingFiles) {
            return array();
        }

        // Sort by mtime
        usort($matchingFiles, function ($a, $b) {
            return filemtime($a) - filemtime($b);
        });
        return $matchingFiles;
    }


    // MWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWM
    // HELPERS
    // MWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWM
    /**
     * Returns the "options" configuration from the TypoScript of the current page
     *
     * @return array
     */
    public function getPluginLevelOptions()
    {
        // Get the options
        $pluginLevelOptions = array(
            'output' => $this->getCurrentOutputFilename()
        );
        if (isset($this->configuration['options.'])) {
            $pluginLevelOptions = $this->configuration['options.'];
        }

        // Check for the development mode
        $pluginLevelOptions['debug'] = $this->isDevelopment();
        return $pluginLevelOptions;
    }

    /**
     * Returns if development mode is on
     *
     * @return boolean
     */
    public function isDevelopment()
    {
        if (isset($this->configuration['development'])) {
            return (bool)intval($this->configuration['development']);
        }
        return false;
    }

    /**
     * Returns if experimental features are enabled
     *
     * @return boolean
     */
    public function getExperimental()
    {
        if ($this->experimental === -1) {
            if (isset($this->configuration['livereload.']) && isset($this->configuration['livereload.']['add_javascript'])) {
                $this->experimental = (bool)$this->configuration['livereload.']['add_javascript'];
            }
            if (isset($this->configuration['experimental']) && (bool)$this->configuration['experimental']) {
                $this->experimental = true;
            }
        }
        return $this->experimental;
    }



    // MWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWM
    // READING AND WRITING THE CACHE
    // MWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWM
    /**
     * Returns the value for the given identifier in the cache
     *
     * @param string $identifier Identifier key
     * @return mixed
     */
    protected function getCache($identifier)
    {
        $identifier = sha1(ConfigurationUtility::getDomainIdentifier() . '-' . $identifier);
        AsseticGeneralUtility::pd(ConfigurationUtility::getDomainIdentifier() . '-' . $identifier);

        if (is_callable('apc_fetch')) {
            return apc_fetch($identifier);
        }

        $cacheInstance = $this->getCacheManager()->getCache('assetic_cache');
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
    protected function setCache($identifier, $value)
    {
        $identifier = sha1(ConfigurationUtility::getDomainIdentifier() . '-' . $identifier);
        AsseticGeneralUtility::pd(ConfigurationUtility::getDomainIdentifier() . '-' . $identifier);

        // AsseticGeneralUtility::pd('setCache', $identifier, $value);
        if (is_callable('apc_store')) {
            apc_store($identifier, $value);
        } else {
            $tags = array();
            $lifetime = 60 * 60 * 24; // * 365 * 10;

            $cacheInstance = $this->getCacheManager()->getCache('assetic_cache');
            if (!$cacheInstance) {
                return;
            }
            $cacheInstance->set($identifier, $value, $tags, $lifetime);
        }
    }

    /**
     * Returns the Cache Manager
     *
     * @return \TYPO3\CMS\Core\Cache\CacheManager
     */
    protected function getCacheManager()
    {
        if (!$this->cacheManager) {
            $this->cacheManager = GeneralUtility::makeInstance('TYPO3\\CMS\\Core\\Cache\\CacheManager');
        }
        return $this->cacheManager;
    }

    /**
     * Remove the cached hash
     *
     * @return void
     */
    public function clearHashCache()
    {
        $this->setCache(self::CACHE_IDENTIFIER_HASH . '_' . $this->getCurrentOutputFilenameWithoutHash(), '');
    }
}
