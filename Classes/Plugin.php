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

use Assetic\Asset\AssetCollection;
// use Assetic\Asset\FileAsset;
// use Assetic\Asset\GlobAsset;

use Assetic\Factory\AssetFactory;
use Assetic\AssetWriter;
use Assetic\AssetManager;
use Assetic\FilterManager;
use Assetic\Filter;

/**
 * Assetic Plugin
 *
 * @package Cundd_Assetic
 */
class Plugin {
	/**
	 * Cache identifier for the hash
	 */
	const CACHE_IDENTIFIER_HASH = 'cundd_assetic_cache_identifier_hash';

	/**
	 * @var \tslib_content
	 */
	public $cObj;

	/**
	 * Indicates if the assets will compile
	 * @var boolean
	 */
	protected $willCompile = -1;

	/**
	 * Assetic asset manager
	 * @var \Assetic\AssetManager
	 */
	protected $assetManager;

	/**
	 * Assetic filter manager
	 * @var FilterManager
	 */
	protected $filterManager;

	/**
	 * @var array
	 */
	protected $configuration;

	/**
	 * The name of the output file
	 * @var string
	 */
	protected $outputFileName;

	/**
	 * Path to the output file directory
	 * @var string
	 */
	protected $outputFileDir = 'typo3temp/cundd_assetic/';

	/**
	 * Previous hash
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
	 * Output configured stylesheets as link tags
	 *
	 * Some processing will be done according to the TypoScript setup of the stylesheets.
	 *
	 * @param string $content
	 * @param array $conf
	 * @return string
	 * @author Daniel Corn <info@cundd.net>
	 */
	public function main($content, $conf) {
		$this->profile('Cundd Assetic plugin begin');
		$this->configuration = $conf;

		// Check if the assets should be compiled
		if ($this->willCompile()) {
			$this->collectAssets();
			$renderedStylesheet = $this->compile();
		} else {
			$renderedStylesheet = $this->getOutputFileDir() . $this->getCurrentOutputFilename();

			/*
			 * Check if the expected output file exists. If it doesn't, set
			 * willCompile to TRUE and call the main routine again
			 */
			$absolutePathToRenderedFile = $this->getPathToWeb() . $renderedStylesheet;
			if (!file_exists($absolutePathToRenderedFile)) {
				$this->forceCompile();
				return $this->main($content, $conf);
			}
			$this->pd($this->getOutputFileDir() . $this->getCurrentOutputFilename(), $this->getOutputFileDir(), $this->getCurrentOutputFilename());
		}
		$content .= '<link rel="stylesheet" type="text/css" href="' . $renderedStylesheet . '" media="all">';
		$content .= $this->getLiveReloadCode();

		$this->profile('Cundd Assetic plugin end');
		return $content;
	}

	/**
	 * Collects all the assets and adds them to the asset manager
	 *
	 * @throws \LogicException if the assetic classes could not be found
	 * @return \Assetic\Asset\AssetCollection
	 */
	public function collectAssets() {
		$this->profile('Will collect assets');
		$pathToWeb = $this->getPathToWeb();
		$pluginLevelOptions = $this->getPluginLevelOptions();

		// Check if the Assetic classes are available
		if (!class_exists('Assetic\\Asset\\AssetCollection', TRUE)) {
			throw new \LogicException('The Assetic classes could not be found', 1356543545);
		}
		$assetManager = $this->getAssetManager();
		$assetCollection = new AssetCollection();
		$factory = new AssetFactory($pathToWeb);
		$this->filterManager = new FilterManager();

		// Register the filter manager
		$factory->setFilterManager($this->filterManager);

		// Loop through all configured stylesheets
		$stylesheets = $this->configuration['stylesheets.'];
		foreach ($stylesheets as $assetKey => $stylesheet) {
			if (!is_array($stylesheet)) {
				$asset = NULL;
				$filter = NULL;
				$assetFilters = array();
				$currentOptions = array();
				$stylesheetType = '';
				$stylesheetConf = is_array($this->configuration['stylesheets.'][$assetKey . '.']) ? $this->configuration['stylesheets.'][$assetKey . '.'] : array();

				// Get the type to find the according filter
				if (isset($stylesheetConf['type'])) {
					$stylesheetType = $stylesheetConf['type'] . '';
				} else {
					$stylesheetType = substr(strrchr($stylesheet, '.'), 1);
				}


				$this->pd($stylesheet);
				$stylesheet = \t3lib_div::getFileAbsFileName($stylesheet);
				$this->pd($stylesheet);

				// Make sure the filter manager nows the filter
				if (!$this->filterManager->has($stylesheetType)) {
					$filter = $this->getFilterForType($stylesheetType);
					if ($filter) {
						$this->filterManager->set($stylesheetType, $this->getFilterForType($stylesheetType));
						$assetFilters = array($stylesheetType);
					}
				} else {
					$assetFilters = array($stylesheetType);
				}

				// Check if there are filter functions
				if (isset($stylesheetConf['functions.'])) {
					if (!$filter) {
						$filter = $this->getFilterForType($stylesheetType);
					}
					$this->applyFunctionsToFilterForType($filter, $stylesheetConf, $stylesheetType);
				}

				// Check if there are special options for this stylesheet
				if (isset($stylesheetConf['options.'])) {
					$currentOptions = $stylesheetConf['options.'];
				} else {
					$currentOptions = $pluginLevelOptions;
				}
				$this->pd($currentOptions);

				$asset = $factory->createAsset(
					array($stylesheet),
					$assetFilters,
					$currentOptions
				);
				$assetCollection->add($asset);
			}
		}

		// Set the output file name

		$this->profile('Set output file ' . $this->getCurrentOutputFilenameWithoutHash());
		$assetCollection->setTargetPath($this->getCurrentOutputFilenameWithoutHash());
		$assetManager->set('cundd_assetic', $assetCollection);
		$this->profile('Did collect assets');
		return $assetCollection;
	}

	/**
	 * Collects the files and tells assetic to compile the files
	 *
	 * @throws \Exception if an exception is thrown during rendering
	 * @return string Returns the path to the compiled file
	 */
	public function compile() {
		$absolutePathToRenderedFiles = $this->getPathToWeb() . $this->getOutputFileDir();
		$writer = new AssetWriter($absolutePathToRenderedFiles);

		// Write the new file if something changed
		if ($this->willCompile()) {
			$this->collectPreviousFilteredAssetFilesAndRemoveSymlink();
			$this->profile('Will compile asset');
			#if ($assetCollection->getLastModified() > filemtime($this->getOutputFileDir() . $pluginLevelOptions['output'])) {
			try {
				$writer->writeManagerAssets($this->getAssetManager());
			} catch (\Exception $exception) {
				if ($this->isDevelopment()) {
					if (is_a($exception, 'Exception_ScssException')) {
						$this->pd($exception->getUserInfo());
					}
					throw $exception;

				} else if (defined('TYPO3_DLOG') && TYPO3_DLOG) {
					$output = 'Caught exception #' . $exception->getCode() . ': ' . $exception->getMessage();
					\t3lib_div::devLog($output, 'assetic');
				}
			}
			#}
			$this->profile('Did compile asset');
			return $this->getOutputFileDir() . $this->moveTempFileToFileWithHash();
		}
		return '';
	}

	/**
	 * Previously filtered asset files that will be removed
	 *
	 * @var array
	 */
	protected $filesToRemove = array();



	/**
	 * Moves the filtered temporary file to the path with the hash in the name
	 *
	 * @return string Returns the new file name
	 */
	protected function moveTempFileToFileWithHash() {
		$fileHash = '';
		$finalFileName = '';

		// $hashAlgorithm = 'crc32';
		// $hashAlgorithm = 'sha1';
		$hashAlgorithm = 'md5';

		$outputFilenameWithoutHash = $this->getCurrentOutputFilenameWithoutHash();
		$outputFileDir = $this->getPathToWeb() . $this->getOutputFileDir();
		$outputFileTempPath = $outputFileDir . $outputFilenameWithoutHash;
		$outputFileFinalPath = '';

		// Create the file hash and store it in the cache
		$this->profile('Will create file hash');

		$fileHash = hash_file($hashAlgorithm, $outputFileTempPath);
		$this->profile('Did create file hash');
		$this->setCache(self::CACHE_IDENTIFIER_HASH . '_' . $outputFilenameWithoutHash, $fileHash);
		$finalFileName = $outputFilenameWithoutHash . '_' . $fileHash . '.css';

		$this->_setCurrentOutputFilename($finalFileName);
		$outputFileFinalPath = $outputFileDir . $finalFileName;

		$this->removePreviousFilteredAssetFiles();

		// Move the temp file to the new file
		$this->profile('Will move compiled asset');
		rename($outputFileTempPath, $outputFileFinalPath);
		$this->profile('Did move compiled asset');

		$this->createSymlinkToFinalPath($outputFileFinalPath);

		return $finalFileName;
	}

	/**
	 * Invokes the functions of the filter
	 *
	 * @param  Filter\FilterInterface $filter                     The filter to apply to
	 * @param  array                  $stylesheetConfiguration    The stylesheet configuration
	 * @param  string                 $stylesheetType             The stylesheet type
	 * @throws \UnexpectedValueException if the given stylesheet type is invalid
	 * @return Filter\FilterInterface                            Returns the filter
	 */
	protected function applyFunctionsToFilterForType($filter, $stylesheetConfiguration, $stylesheetType) {
		if (!$stylesheetType) {
			throw new \UnexpectedValueException('The given stylesheet type is invalid "' . $stylesheetType . '"', 1355910725);
		}
		$functions = $stylesheetConfiguration['functions.'];
		ksort($functions);
		foreach ($functions as $function => $data) {
			if (!is_array($data)) {
				$data = array($data);
			}
			$this->prepareFunctionParameters($data);

			// Check if the function has a numerator as prefix strip that off
			if ($function[1] === '-' && is_numeric($function[0])) {
				$function = substr($function, 2);
			}

			$this->pd("Call function $function on filter", $filter, $data);
			if (is_callable(array($filter, $function))) {
				call_user_func_array(array($filter, $function), $data);
			} else {
				trigger_error('Filter does not implement ' . $function, E_USER_NOTICE);
			}
		}
		$this->pd($filter);
		$this->filterManager->set($stylesheetType, $filter);
		return $filter;
	}

	/**
	 * Prepares the data to be passed to a filter function.
	 *
	 * I.e. expands paths to their absolute path.
	 *
	 * @param  array $parameters Reference to the data array
	 * @return void
	 */
	protected function prepareFunctionParameters(&$parameters) {
		foreach ($parameters as &$parameter) {
			if (strpos($parameter, '.') !== FALSE || strpos($parameter, DIRECTORY_SEPARATOR) !== FALSE) {
				$parameter = \t3lib_div::getFileAbsFileName($parameter);
			}
		}
	}

	/**
	 * Returns the code for "live reload"
	 *
	 * @return string
	 */
	protected function getLiveReloadCode() {
		if (!$this->getExperimental() || !$this->isBackendUser()) {
			return '';
		}

		$code = '
		<script type="text/javascript">
		(function () {
			var asseticInterval = window.asseticInterval || 1750,
				Assetic = window.Assetic || {},
				Ef = function () {},
				console = window.console || { log: Ef, info: Ef },
				length;

			Assetic.stylesheets = [];
			Assetic.originalUrls = [];
			Assetic.lastId = "";


			function load(url, callback) {
				var xhr = new XMLHttpRequest();
				xhr.onreadystatechange = ensureReadiness;

				function ensureReadiness() {
					if (xhr.readyState < 4) {
						return;
					}
					if (xhr.status !== 200) {
						return;
					}
					if (xhr.readyState === 4) {
						callback(xhr);
					}
				}

				xhr.open("HEAD", url, true);
				xhr.send("");
			}

			if (typeof document.querySelectorAll === "function") {
				Assetic.stylesheets = document.querySelectorAll("link[href*=cundd_assetic]");
				length = Assetic.stylesheets.length;

				for (var i = 0; i < length; i++) {
					var originalUrl = "", lastSlashPosition,
						stylesheet = Assetic.stylesheets[i];
					originalUrl = stylesheet.href.replace(/__\w*\.css/, "_.css");


					lastSlashPosition = originalUrl.lastIndexOf("/");
					Assetic.originalUrls[i] = originalUrl.substr(0, lastSlashPosition) + "/_debug_" + originalUrl.substr(lastSlashPosition + 1);
				}
			}

			Assetic.reload = function () {
				var stylesheets = Assetic.stylesheets,
					originalUrls = Assetic.originalUrls,
					stylesheet = null,
					originalUrl,
					newUrl;

				for (var i = 0; i < length; i++) {
					stylesheet = stylesheets[i];
					originalUrl = originalUrls[i];
					newUrl = originalUrl + "?reload=" + (+new Date);
					load(newUrl, function (xhr) {
						if (xhr.getResponseHeader("Last-Modified") !== Assetic.lastId) {
							/* console.log(xhr.getResponseHeader("Last-Modified")); */
							/* console.log(xhr.getResponseHeader("ETag")); */
							Assetic.lastId = xhr.getResponseHeader("Last-Modified");
							console.log("Reload " + originalUrl);
							stylesheet.href = newUrl;
						}


					})
				}
			};


			Assetic.start = function () {
				Assetic.asseticIntervalCallback = window.setInterval(Assetic.reload, asseticInterval);
			};
			Assetic.stop = function () {
				window.clearInterval(Assetic.asseticIntervalCallback);
			};

			Assetic.start();
			Assetic.reload();

			window.Assetic = Assetic;
		})();
		</script>';
		return str_replace(array('  ', "\t", PHP_EOL), ' ', $code);
	}

	/**
	 * Force the recompilation
	 * @return void
	 */
	public function forceCompile() {
		$this->willCompile = TRUE;
	}

	/**
	 * Returns if the files should be compiled
	 * @return boolean
	 */
	public function willCompile() {
		if ($this->willCompile === -1) {
			// If no backend user is logged in, check if it is allowed
			if (!$this->isBackendUser()) {
				$this->pd('no BE_USER, is dev:', $this->isDevelopment(),
					(bool) ($this->isDevelopment() * intval($this->configuration['allow_compile_without_login'])));

				$this->willCompile = (bool) ($this->isDevelopment() * intval($this->configuration['allow_compile_without_login']));
			} else {
				$this->pd('has BE_USER, is dev:', $this->isDevelopment());
				$this->willCompile = $this->isDevelopment();
			}
		}
		return $this->willCompile;
	}

	/**
	 * Sets the configuration
	 * @param array $configuration
	 * @return void
	 */
	public function setConfiguration($configuration) {
		$this->configuration = $configuration;
	}

	/**
	 * Returns the configuration
	 * @return array
	 */
	public function getConfiguration() {
		return $this->configuration;
	}

	/**
	 * Sets the path to the output file directory
	 * @param string $outputFileDir
	 */
	public function setOutputFileDir($outputFileDir) {
		$this->outputFileDir = $outputFileDir;
	}

	/**
	 * Returns the path to the output file directory
	 * @return string $outputFileDir
	 */
	public function getOutputFileDir() {
		return $this->outputFileDir;
	}

	/**
	 * Returns the shared asset manager
	 * @return \Assetic\AssetManager
	 */
	public function getAssetManager() {
		if (!$this->assetManager) {
			$this->assetManager = new AssetManager();
		}
		return $this->assetManager;
	}

	/**
	 * Returns the current output filename
	 * @return string
	 */
	public function getOutputFilePath() {
		return $this->getOutputFileDir() . $this->getCurrentOutputFilename();
	}

	/**
	 * Returns the current output filename without the hash
	 * @return string
	 */
	public function getCurrentOutputFilenameWithoutHash() {
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
		return $outputFileName;
	}

	/**
	 * Returns the current output filename
	 *
	 * The current output filename may be changed if when the hash of the
	 * filtered asset file is generated
	 * @return string
	 */
	public function getCurrentOutputFilename() {
		if (!$this->outputFileName) {
			// Add a hash for caching
			$newHash = $this->getHash();
			$this->outputFileName = $this->getCurrentOutputFilenameWithoutHash();
			$this->outputFileName .= '_' . $newHash;
			$this->outputFileName .= '.css';
			$this->pd($this->outputFileName);
		}
		$this->pd($this->outputFileName);
		return $this->outputFileName;
	}

	/**
	 * Sets the current output filename
	 * @param string $outputFileName
	 * @return string
	 */
	protected function _setCurrentOutputFilename($outputFileName) {
		$this->outputFileName = $outputFileName;
	}


	/**
	 * Returns the hash for the current asset version
	 * @return string
	 */
	protected function getHash() {
		$entry = $this->getPreviousHash();

		// If $entry is null, it hasn't been cached. Calculate the value and store it in the cache:
		if ($this->willCompile() || !$entry) {
			$entry = '';#time();

			// Save value in cache
			$this->setCache(self::CACHE_IDENTIFIER_HASH . '_' . $this->getCurrentOutputFilenameWithoutHash(), $entry);
		}
		$this->pd($entry);
		return $entry;
	}

	/**
	 * Returns the hash from the cache, or an emptry string if it wasn't set.
	 * @return string
	 */
	protected function getPreviousHash() {
		if (!$this->previousHash) {
			$suffix = '.css';
			$filePath = $this->getOutputFileDir() . $this->getCurrentOutputFilenameWithoutHash();

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
	public function collectPreviousFilteredAssetFilesAndRemoveSymlink() {
		$this->removeSymlink();
		$this->collectPreviousFilteredAssetFiles();
	}

	/**
	 * Collect the previous filtered Asset files
	 */
	public function collectPreviousFilteredAssetFiles() {
		$suffix = '.css';
		$outputFileDir = $this->getPathToWeb() . $this->getOutputFileDir();
		$filePath = $outputFileDir . $this->getCurrentOutputFilenameWithoutHash();
		$this->filesToRemove = $this->findPreviousFilteredAssetFiles($filePath, $suffix);
	}

	/**
	 * Create the symlink to the given final path
	 *
	 * @param string $fileFinalPath
	 */
	public function createSymlinkToFinalPath($fileFinalPath) {
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
	public function removeSymlink() {
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
	 * Returns the symlink path
	 *
	 * @return string
	 */
	public function getSymlinkPath() {
		return $this->getPathToWeb() . $this->getOutputFileDir() . '_debug_' . $this->getCurrentOutputFilenameWithoutHash() . '.css';
	}

	/**
	 * Remove the previous filtered Asset files
	 *
	 * @return boolean	Returns TRUE if the file was removed, otherwise FALSE
	 */
	public function removePreviousFilteredAssetFiles() {
		$success = TRUE;
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
	 * @param string $filePath
	 * @param string $suffix
	 * @return array
	 */
	protected function findPreviousFilteredAssetFiles($filePath, $suffix = '.css') {
		$this->profile('Will call glob');
		$matchingFiles = glob($filePath . '_' . '*' . $suffix);
		$this->profile('Did call glob');

		if (!$matchingFiles) {
			return array();
		}

		// Sort by mtime
		usort($matchingFiles, function($a, $b) { return filemtime($a) - filemtime($b); });
		return $matchingFiles;
	}



	// MWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWM
	// HELPERS
	// MWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWM
	/**
	 * Returns the "options" configuration from the TypoScript of the current
	 * page.
	 * @return array
	 */
	public function getPluginLevelOptions() {
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
	 * Returns the path to the web directory
	 * @return string
	 */
	public function getPathToWeb() {
		return defined('PATH_site') ? PATH_site : '';
	}

	/**
	 * Returns if development mode is on
	 * @return boolean
	 */
	public function isDevelopment() {
		if (isset($this->configuration['development'])) {
			return (bool) intval($this->configuration['development']);
		}
		return FALSE;
	}

	/**
	 * Returns if a backend user is logged in
	 *
	 * @return bool
	 */
	public function isBackendUser() {
		if (!isset($GLOBALS['BE_USER'])
			|| !isset($GLOBALS['BE_USER']->user)
			|| !intval($GLOBALS['BE_USER']->user['uid'])) {
			return FALSE;
		}
		return TRUE;
	}

	/**
	 * Returns if experimental features are enabled
	 *
	 * @return boolean
	 */
	public function getExperimental() {
		if ($this->experimental === -1) {
			$this->experimental = (bool) $this->configuration['experimental'];
		}
		return $this->experimental;
	}


	// MWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWM
	// FILTERS
	// MWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWM
	/**
	 * Returns the right filter for the given file type
	 *
	 * @param  string $type The file type
	 * @throws \LogicException if the required filter class does not exist
	 * @return Filter\FilterInterface       The filter
	 */
	protected function getFilterForType($type) {
		// If the filter manager has an according filter return it
		if ($this->filterManager->has($type)) {
			return $this->filterManager->get($type);
		}

		$filter = NULL;
		$filterBinaryPath = NULL;
		$filterClass = ucfirst($type) . 'Filter';
		$filterBinaries = $this->configuration['filter_binaries.'];
		$filterForTypeDefinitions = $this->configuration['filter_for_type.'];

		// Check which filter should be used for the given type. This allows the
		// user i.e. to use lessphp for LESS files.
		if (isset($filterForTypeDefinitions[$type])) {
			$filterClass = $filterForTypeDefinitions[$type];
		}

		// Check if no filter should be used
		if ($filterClass === 'none') {
			return NULL;
		}

		// Replace the backslash in the filter class with an underscore
		$filterClassIdentifier = strtolower(str_replace('\\', '_', $filterClass));
		if (isset($filterBinaries[$filterClassIdentifier])) {
			$filterBinaryPath = $filterBinaries[$filterClassIdentifier];
		}

		if (class_exists($filterClass)) {
			if ($filterBinaryPath) {
				$filter = new $filterClass($filterBinaryPath);
			} else {
				$filter = new $filterClass();
			}
		} else {
			throw new \LogicException('Filter class ' . $filterClass . ' not found', 1355846301);
		}
		return $filter;
	}


	// MWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWM
	// READING AND WRITING THE CACHE
	// MWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWM
	/**
	 * Returns the value for the given identifier in the cache
	 * @param string $identifier Identifier key
	 * @return mixed
	 */
	protected function getCache($identifier) {
		// $this->pd('getCache', $identifier);
		if (is_callable('apc_fetch')) {
			return apc_fetch($identifier);
		}
		$cacheInstance = $GLOBALS['typo3CacheManager']->getCache('assetic_cache');
		return $cacheInstance->get($identifier);
	}

	/**
	 * Stores the value for the given identifier in the cache
	 * @param string $identifier Identifier key
	 * @param mixed $value      Value to store
	 */
	protected function setCache($identifier, $value) {
		// $this->pd('setCache', $identifier, $value);
		if (is_callable('apc_store')) {
			apc_store($identifier, $value);
		} else {
			$tags = array();
 			$lifetime = 60 * 60 * 24; // * 365 * 10;

			$cacheInstance = $GLOBALS['typo3CacheManager']->getCache('assetic_cache');
			$cacheInstance->set($identifier, $value, $tags, $lifetime);
		}
	}

	/**
	 * Remove the cached hash
	 * @return void
	 */
	public function clearHashCache() {
		$this->setCache(self::CACHE_IDENTIFIER_HASH . '_' . $this->getCurrentOutputFilenameWithoutHash(), '');
	}




	// MWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWM
	// DEBUGGING AND PROFILING
	// MWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWM
	/**
	 * Dumps a given variable (or the given variables) wrapped into a 'pre' tag.
	 *
	 * @param	mixed	$var1
	 * @return	string The printed content
	 */
	public function pd($var1 = '__iresults_pd_noValue') {
		static $willDebug = -1;
		if ($willDebug === -1) {
			$willDebug = FALSE;
			if (
				(isset($_GET['cundd_assetic_debug']) && $_GET['cundd_assetic_debug'])
				|| (isset($_POST['cundd_assetic_debug']) && $_POST['cundd_assetic_debug'])
				) {
				$willDebug = TRUE;
			}
		}

		if (class_exists('Tx_Iresults') && $willDebug) {
			$arguments = func_get_args();
			call_user_func_array(array('Tx_Iresults', 'pd'), $arguments);
		}
	}

	/**
	 * Print a profiling message.
	 *
	 * @param	string $msg
	 * @return	string The printed content
	 */
	public function profile($msg = '') {
		if (class_exists('Tx_Iresults_Profiler')) {
			\Tx_Iresults_Profiler::profile($msg);
		}
	}
}

class_alias('Cundd\\Assetic\\Plugin', 'Tx_Cundd_Assetic_Plugin', FALSE);
?>