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
	 * @var tslib_content
	 */
	public $cObj;

	/**
	 * @var array
	 */
	protected $conf;

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
		$this->conf = $conf;

		$outputFileName = 'styles.css';
		if (isset($conf['output'])) {
			$outputFileName = $conf['output'];
		}

		if ($this->isDevelopment()) {
			$renderedStylesheet = $this->compile($outputFileName);
		} else {
			$renderedStylesheet = '/typo3temp/cundd_assetic/' . $outputFileName;
		}
		$content .= '<link rel="stylesheet" type="text/css" href="' . $renderedStylesheet . '" media="all">';

		return $content;
	}

	/**
	 * Collects the files and tells assetic to compile the files
	 *
	 * @param string $outputFileName	Name of the output file
	 * @return string Returns the path to the compiled file
	 */
	public function compile($outputFileName) {
		$pathToWeb = defined('PATH_site') ? PATH_site : '';
		$pluginLevelOptions = array();
		$pathToRenderedFiles = $pathToWeb . '/typo3temp/cundd_assetic/';

		if (!class_exists('Assetic\\Asset\\AssetCollection', TRUE)) {
			throw new \LogicException('The Assetic classes could not be found', 1356543545);
		}
		$assetCollection = new AssetCollection();
		$assetManager = new AssetManager();
		$writer = new AssetWriter($pathToRenderedFiles);
		$factory = new AssetFactory($pathToWeb);
		$this->filterManager = new FilterManager();

		// Register the filter manager
		$factory->setFilterManager($this->filterManager);

		// Get the options
		$pluginLevelOptions = array(
			'output' => $outputFileName
		);
		if (isset($this->conf['options.'])) {
			$pluginLevelOptions = $this->conf['options.'];
		}

		// Check for the development mode
		$pluginLevelOptions['debug'] = $this->isDevelopment();

		// Loop through all configured stylesheets
		foreach ($this->conf['stylesheets.'] as $assetKey => $stylesheet) {
			if (!is_array($stylesheet)) {
				$asset = NULL;
				$filter = NULL;
				$assetFilters = array();
				$currentOptions = array();
				$stylesheetType = '';
				$stylesheetConf = is_array($this->conf['stylesheets.'][$assetKey . '.']) ? $this->conf['stylesheets.'][$assetKey . '.'] : array();

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
		$assetCollection->setTargetPath($pluginLevelOptions['output']);
		$assetManager->set('cundd_assetic', $assetCollection);

		// Write the new file if something changed
		#if ($assetCollection->getLastModified() > filemtime('/typo3temp/cundd_assetic/' . $pluginLevelOptions['output'])) {
		try {
			$writer->writeManagerAssets($assetManager);
		} catch (\Exception $exception) {
			if ($this->isDevelopment()) {
				throw $exception;
			} else if (defined('TYPO3_DLOG') && TYPO3_DLOG) {
				$output = 'Caught exception #' . $exception->getCode() . ': ' . $exception->getMessage();
				\t3lib_div::devLog($output);
			}
		}
		#}
		return '/typo3temp/cundd_assetic/' . $pluginLevelOptions['output'];
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
		if (isset($this->conf['development'])) {
			return (bool) intval($this->conf['development']);
		}
		return FALSE;
	}

	// /**
	//  * Apply the custom filter binary paths defined in the TypoScript.
	//  * @return void
	//  */
	// protected function configureFilters() {
	// 	if (!isset($this->conf['filter_binaries.'])) {
	// 		return;
	// 	}

	// 	// Loop through all the configured paths
	// 	$filterBinaries = $this->conf['filter_binaries.'];
	// 	foreach ($filterBinaries as $filter => $filterBinaryPath) {
	// 		$filterClassPrefixForType = $this->filterClassPrefix;
	// 		if ($filter === 'sass' || $filter === 'scss') {
	// 			$filterClassPrefixForType .= 'Sass\\';
	// 		}
	// 		$filterClass = $filterClassPrefixForType . ucfirst($filter) . 'Filter';

	// 		$this->pd($filter, $filterBinaryPath);

	// 		$this->filterManager->set($filter, new $filterClass($filterBinaryPath));
	// 	}


	// }

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
		$filterBinaries = $this->conf['filter_binaries.'];
		$filterForTypeDefinitions = $this->conf['filter_for_type.'];

		// Check which filter should be used for the given type. This allows the
		// user i.e. to use lessphp for LESS files.
		if (isset($filterForTypeDefinitions[$type])) {
			$filterClass = $filterForTypeDefinitions[$type];
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


	/**
	 * Dumps a given variable (or the given variables) wrapped into a 'pre' tag.
	 *
	 * @param	mixed	$var1
	 * @return	string The printed content
	 */
	public function pd($var1 = '__iresults_pd_noValue') {
		if (class_exists('Tx_Iresults')) {
			$arguments = func_get_args();
			call_user_func_array(array('Tx_Iresults', 'pd'), $arguments);
		}
	}
}
?>