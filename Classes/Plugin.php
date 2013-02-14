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
ini_set('display_errors', TRUE);

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
	 * @var tslib_content
	 */
	public $cObj;

	/**
	 * Assetic asset manager
	 * @var Assetic\AssetManager
	 */
	protected $assetManager;

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
		if ($this->willCompile()) {
			$this->collectAssets();
			$renderedStylesheet = $this->compile();
		} else {
			$renderedStylesheet = $this->getOutputFileDir() . $this->getCurrentOutputFilename();
		}
		$content .= '<link rel="stylesheet" type="text/css" href="' . $renderedStylesheet . '" media="all">';
		$this->profile('Cundd Assetic plugin end');
		return $content;
	}

	/**
	 * Collects all the assets and adds them to the asset manager
	 * @return Assetic\Asset\AssetCollection
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

		$this->profile('Set output file ' . $this->getCurrentOutputFilename());
		$assetCollection->setTargetPath($this->getCurrentOutputFilename());
		$assetManager->set('cundd_assetic', $assetCollection);
		$this->profile('Did collect assets');
		return $assetCollection;
	}

	/**
	 * Collects the files and tells assetic to compile the files
	 * @return string Returns the path to the compiled file
	 */
	public function compile() {
		$absolutePathToRenderedFiles = $this->getPathToWeb() . $this->getOutputFileDir();
		$writer = new AssetWriter($absolutePathToRenderedFiles);

		// Write the new file if something changed
		if ($this->willCompile()) {
			$this->removePreviousFilteredAssetFile();
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
					\t3lib_div::devLog($output);
				}
			}
			#}
			$this->profile('Did compile asset');
		}
		return $this->getOutputFileDir() . $this->moveTempFileToFileWithHash();
	}

	/**
	 * Moves the filtered temporary file to the path with the hash in the name.
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
		$outputFileTempPath = $outputFileDir . $this->getCurrentOutputFilename();
		$outputFileFinalPath = '';

		// Create the file hash and store it in the cache
		$this->profile('Will create file hash');
		$fileHash = hash_file($hashAlgorithm, $outputFileTempPath);
		$this->profile('Did create file hash');
		$this->setCache(self::CACHE_IDENTIFIER_HASH . '_' . $outputFilenameWithoutHash, $fileHash);
		$finalFileName = $outputFilenameWithoutHash . '_' . $fileHash . '.css';

		$this->_setCurrentOutputFilename($finalFileName);
		$outputFileFinalPath = $outputFileDir . $finalFileName;

		// Move the temp file to the new file
		$this->profile('Will move compiled asset');
		rename($outputFileTempPath, $outputFileFinalPath);
		$this->profile('Did move compiled asset');
		return $finalFileName;
	}

	/**
	 * Invokes the functions of the filter
	 * @param  Filter $filter					The filter to apply to
	 * @param  array $stylesheetConfiguration	The stylesheet configuration
	 * @param  string $stylesheetType			The stylesheet type
	 * @return Filter							Returns the filter
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
			$result = call_user_func_array(array($filter, $function), $data);
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
		foreach ($parameters as $key => &$parameter) {
			if (strpos($parameter, '.') !== FALSE || strpos($parameter, DIRECTORY_SEPARATOR) !== FALSE) {
				$parameter = \t3lib_div::getFileAbsFileName($parameter);
			}
		}
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
	 * Returns if the files should be compiled
	 * @return boolean
	 */
	public function willCompile() {
		// If no backend user is logged in, check if it is allowed
		if (!isset($GLOBALS['BE_USER'])
			|| !isset($GLOBALS['BE_USER']->user)
			|| !intval($GLOBALS['BE_USER']->user['uid'])) {

			$this->pd('no BE_USER, is dev:', $this->isDevelopment(),
				(bool) ($this->isDevelopment() * intval($this->configuration['allow_compile_without_login'])));

			return (bool) ($this->isDevelopment() * intval($this->configuration['allow_compile_without_login']));
		}
		$this->pd('has BE_USER, is dev:', $this->isDevelopment());
		return $this->isDevelopment();
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
	 * @return Assetic\AssetManager
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

		$this->pd($this->willCompile(),
			$this->getPreviousHash(),
			($this->willCompile() || FALSE === ($entry = $this->getPreviousHash())),
			(FALSE === ($entry = $this->getPreviousHash()))
			);

		$entry = $this->getPreviousHash();

		// If $entry is null, it hasn't been cached. Calculate the value and store it in the cache:
		if ($this->willCompile() || !$entry) {
			$entry = time();

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
		$suffix = '.css';
		$filepath = $this->getOutputFileDir() . $this->getCurrentOutputFilenameWithoutHash();

		$previousHash = '' . $this->getCache(self::CACHE_IDENTIFIER_HASH . '_' . $this->getCurrentOutputFilenameWithoutHash());
		$previousHashFilePath = $filepath . '_' . $previousHash . $suffix;

		if (!$previousHash || !file_exists($previousHashFilePath)) {
			//$matchingFiles = glob($filepath . '_' . '[abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789]' . '.css');
			$matchingFiles = glob($filepath . '_' . '*' . $suffix);
			// echo '<pre>';
			// var_dump($matchingFiles);
			// echo '</pre>';

			if (!$matchingFiles) {
				return '';
			}
			$lastMatchingFile = end($matchingFiles);
			$previousHash = substr($lastMatchingFile, strlen($filepath) + 1, (-1 * strlen($suffix)));
		}
		return $previousHash;
	}

	/**
	 * Remove the previous filtered asset file
	 * @return boolean	Returns TRUE if the file was removed, otherwise FALSE
	 */
	public function removePreviousFilteredAssetFile() {
		$previousHash = $this->getPreviousHash();

		$this->pd($previousHash, 'Remove');
		if ($previousHash) {
			$this->pd('Remove');

			$oldFilteredAssetFile = $this->getOutputFileDir() . $this->getCurrentOutputFilenameWithoutHash() . '_' . $previousHash . '.css';
			return unlink($oldFilteredAssetFile);
		}
		return FALSE;
	}

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
	 * Returns the right filter for the given file type
	 * @param  string $type The file type
	 * @return Filter       The filter
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