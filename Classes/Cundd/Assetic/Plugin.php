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
	 * The prefix for filter class names
	 * @var string
	 */
	protected $filterClassPrefix = 'Assetic\\Filter\\';

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
		$targetName = 'styles.css';

		if ($this->isDevelopment()) {
			$renderedStylesheet = $this->compile($targetName);
		} else {
			$renderedStylesheet = '/typo3temp/cundd_assetic/' . $targetName;
		}
		$content .= '<link rel="stylesheet" type="text/css" href="' . $renderedStylesheet . '" media="all">';

		return $content;
	}

	/**
	 * Collects the files and tells assetic to compile the files
	 *
	 * @param string $targetName	Name of the output file
	 * @return string Returns the path to the compiled file
	 */
	public function compile($targetName) {
		$options = array();
		$pathToWeb = defined('PATH_site') ? PATH_site : '';
		$pathToRenderedFiles = $pathToWeb . '/typo3temp/cundd_assetic/';

		$assetCollection = new AssetCollection();
		$assetManager = new AssetManager();
		$writer = new AssetWriter($pathToRenderedFiles);
		$factory = new AssetFactory($pathToWeb);
		$this->filterManager = new FilterManager();

		// Register the filter manager
		$factory->setFilterManager($this->filterManager);

		// Get the options
		$options = array(
			'output' => $targetName
		);
		if (isset($this->conf['options.'])) {
			$options = $this->conf['options.'];
		}

		// Check for the development mode
		$options['debug'] = $this->isDevelopment();

		// Loop through all configured stylesheets
		foreach ($this->conf['stylesheets.'] as $assetKey => $stylesheet) {
			if (!is_array($stylesheet)) {
				$asset = NULL;
				$assetFilters = array();
				$stylesheetType = '';
				$stylesheetConf = is_array($this->conf['stylesheets.'][$assetKey . '.']) ? $this->conf['stylesheets.'][$assetKey . '.'] : array();

				// Get the type to find the according filter
				if (isset($stylesheetConf['type'])) {
					$stylesheetType = $stylesheetConf['type'] . '';
				} else {
					$stylesheetType = substr(strrchr($stylesheet, '.'), 1);
				}

				$stylesheet = $pathToWeb . $stylesheet;

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

				$asset = $factory->createAsset(
					array($stylesheet),
					$assetFilters,
					$options
				);
				$assetCollection->add($asset);
			}
		}

		// Set the output file name
		$assetCollection->setTargetPath($options['output']);
		$assetManager->set('cundd_assetic', $assetCollection);

		// Write the new file if something changed
		if ($assetCollection->getLastModified() > filemtime('/typo3temp/cundd_assetic/' . $options['output'])) {
			try {
				$writer->writeManagerAssets($assetManager);
			} catch (\Exception $exception) {
				if ($this->isDevelopment()) {
					throw $exception;
				} else if (defined('TYPO3_DLOG') && TYPO3_DLOG) {
					$output = 'Caught exception #' . $exception->getCode() . ': ' . $exception->getMessage();
					t3lib_div::devLog($output);
				}
			}
		}
		return '/typo3temp/cundd_assetic/' . $options['output'];
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

		// Add the prefix
		$filterClass = $this->filterClassPrefix . $filterClass;

		if (class_exists($filterClass)) {
			if ($filterBinaryPath) {
				$filter = new $filterClass($filterBinaryPath);
			} else {
				$filter = new $filterClass();
			}
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
		static $counter = 0;
		static $scriptDir = '';
		static $didSetDebug = FALSE;

		$printPathInformation = TRUE;
		$bt = NULL;
		$output = '';
		$printTags = TRUE;
		$printAnchor = TRUE;
		$outputHandling = 0; // 0 = normal, 1 = shell, 2 >= non XML
		$traceLevel = PHP_INT_MAX;

		if ($outputHandling) {
			$printAnchor = FALSE;
			$printTags = FALSE;
			ob_start();
		}

		// Output the dumps
		if ($printTags) {
			if ($printAnchor) {
				echo "<a href='#ir_debug_anchor_bottom_$counter' name='ir_debug_anchor_top_$counter' style='background:#555;color:#fff;font-size:0.6em;'>&gt; bottom</a>";
			}
			echo '<div class="ir_debug_container" style="text-align:left;"><pre class="ir_debug">';
		}
		$args = func_get_args();
		foreach ($args as $var) {
			if ($var !== '__iresults_pd_noValue') {
				var_dump($var);
			}
		}

		$i = 0;
		if ($printPathInformation) {
			$bt = NULL;
			$options = FALSE;
			if (defined('DEBUG_BACKTRACE_IGNORE_ARGS')) {
				$options = DEBUG_BACKTRACE_PROVIDE_OBJECT & DEBUG_BACKTRACE_IGNORE_ARGS;
			}

			if (version_compare(PHP_VERSION, '5.4.0') >= 0) {
				$bt = debug_backtrace($options, 10);
			} else {
				$bt = debug_backtrace($options);
			}

			$function = @$bt[$i]['function'];
			while($function == 'pd' OR $function == 'call_user_func_array' OR
				  $function == 'call_user_func') {
				$i++;
				$function = @$bt[$i]['function'];
			}

			// Set the static trace level
			if ($traceLevel === PHP_INT_MAX) {
				if (isset($_GET['tracelevel'])) {
					$traceLevel = (int) $_GET['tracelevel'];
				} else {
					$traceLevel = -1;
				}
			}
			$i += $traceLevel;

			// Set the static script dir
			if (!$scriptDir) {
				$scriptDir = dirname($_SERVER['SCRIPT_FILENAME']);
			}
			$file = str_replace($scriptDir, '', @$bt[$i]['file']);
			if ($printTags) {
				echo "<span style='font-size:0.8em'>
						<a href='file://" . @$bt[$i]['file'] . "' target='_blank'>" . $file . ' @ ' . @$bt[$i]['line'] . "</a>
					</span>";
			} else if ($outputHandling < 2) {
				echo "\033[0;35m" . $file . ' @ ' . @$bt[$i]['line'] . "\033[0m" . PHP_EOL;
			} else {
				echo $file . ' @ ' . @$bt[$i]['line'] . PHP_EOL;
			}
		}

		if ($printTags) {
			echo '</pre></div>';
			if ($printAnchor) {
				echo "<a href='#ir_debug_anchor_top_$counter' name='ir_debug_anchor_bottom_$counter' style='background:#555;color:#fff;font-size:0.6em;'>&gt; top</a><br />";
				$counter++;
			}
		}

		/*
		 * If the output was captured, read it and write it to the STDOUT.
		 */
		if ($outputHandling) {
			$output = ob_get_contents();
			ob_end_clean();

			if ($outputHandling >= 2) {
				self::say($output);
			} else {
				fwrite(STDOUT, $output);
			}
		}
		return $output;
	}
}
?>