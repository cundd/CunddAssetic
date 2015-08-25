<?php
/*
 *  Copyright notice
 *
 *  (c) 2015 Andreas Thurnheer-Meier <tma@iresults.li>, iresults
 *  Daniel Corn <cod@iresults.li>, iresults
 *
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 */

/**
 * @author COD
 * Created 08.05.15 16:21
 */

namespace Cundd\Assetic\Compiler;

use Assetic\Asset\AssetCollection;
use Assetic\Exception\FilterException;
use Assetic\Factory\AssetFactory;
use Assetic\AssetWriter;
use Assetic\AssetManager;
use Assetic\FilterManager;
use Assetic\Filter;
use Cundd\Assetic\Utility\ConfigurationUtility;
use Cundd\Assetic\Utility\GeneralUtility as AsseticGeneralUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Compiler
 *
 * The class that builds the connection between Assetic and TYPO3
 *
 * @package Cundd\Assetic
 */
class Compiler implements CompilerInterface
{

    /**
     * Assetic asset manager
     *
     * @var AssetManager
     */
    protected $assetManager;

    /**
     * Assetic filter manager
     *
     * @var FilterManager
     */
    protected $filterManager;

    /**
     * Configuration from TYPO3
     *
     * @var array
     */
    protected $configuration = array();

    /**
     * @var array
     */
    protected $pluginLevelOptions = array();


    function __construct($configuration)
    {
        $this->configuration = $configuration;
    }

    /**
     * Collects all the assets and adds them to the asset manager
     *
     * @throws \LogicException if the assetic classes could not be found
     * @return \Assetic\Asset\AssetCollection
     */
    public function collectAssets()
    {
        AsseticGeneralUtility::profile('Will collect assets');
        $pathToWeb = ConfigurationUtility::getPathToWeb();
        $pluginLevelOptions = $this->getPluginLevelOptions();

        // Check if the Assetic classes are available
        if (!class_exists('Assetic\\Asset\\AssetCollection', true)) {
            throw new \LogicException('The Assetic classes could not be found', 1356543545);
        }
        $assetManager = $this->getAssetManager();
        $assetCollection = new AssetCollection();
        $factory = new AssetFactory($pathToWeb);
        $this->filterManager = new FilterManager();

        // Register the filter manager
        $factory->setFilterManager($this->filterManager);

        // Loop through all configured stylesheets
        $stylesheets = isset($this->configuration['stylesheets.']) ? $this->configuration['stylesheets.'] : array();
        foreach ($stylesheets as $assetKey => $stylesheet) {
            if (!is_array($stylesheet)) {
                $asset = null;
                $filter = null;
                $assetFilters = array();
                $stylesheetConf = is_array($this->configuration['stylesheets.'][$assetKey . '.']) ? $this->configuration['stylesheets.'][$assetKey . '.'] : array();

                // Get the type to find the according filter
                if (isset($stylesheetConf['type'])) {
                    $stylesheetType = $stylesheetConf['type'] . '';
                } else {
                    $stylesheetType = substr(strrchr($stylesheet, '.'), 1);
                }


                AsseticGeneralUtility::pd($stylesheet);
                $stylesheet = GeneralUtility::getFileAbsFileName($stylesheet);
                AsseticGeneralUtility::pd($stylesheet);

                // Make sure the filter manager knows the filter
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
                AsseticGeneralUtility::pd($currentOptions);

                $asset = $factory->createAsset(
                    array($stylesheet),
                    $assetFilters,
                    $currentOptions
                );
                $assetCollection->add($asset);
            }
        }

        // Set the output file name
        //AsseticGeneralUtility::profile('Set output file ' . $this->getCurrentOutputFilenameWithoutHash());
        //$assetCollection->setTargetPath($this->getCurrentOutputFilenameWithoutHash());
        $assetManager->set('cundd_assetic', $assetCollection);
        AsseticGeneralUtility::profile('Did collect assets');
        return $assetCollection;
    }

    /**
     * Collects the files and tells assetic to compile the files
     *
     * @throws \Exception if an exception is thrown during rendering
     * @return bool Returns if the files have been compiled successfully
     */
    public function compile()
    {
        $outputDirectory = ConfigurationUtility::getPathToWeb() . ConfigurationUtility::getOutputFileDir();
        GeneralUtility::mkdir($outputDirectory);

        $writer = new AssetWriter($outputDirectory);

        AsseticGeneralUtility::profile('Will compile asset');
        try {
            $writer->writeManagerAssets($this->getAssetManager());
        } catch (FilterException $exception) {
            $this->handleFilterException($exception);
            return false;
        } catch (\Exception $exception) {
            if ($this->isDevelopment()) {
                if (is_a($exception, 'Exception_ScssException')) {
                    /** @var \Exception_ScssException $exception */
                    AsseticGeneralUtility::pd($exception->getUserInfo());
                }

                throw $exception;
            } else {
                if (defined('TYPO3_DLOG') && TYPO3_DLOG) {
                    $output = 'Caught exception #' . $exception->getCode() . ': ' . $exception->getMessage();
                    GeneralUtility::devLog($output, 'assetic');
                }
            }
            return false;
        }

        AsseticGeneralUtility::profile('Did compile asset');
        return true;
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
    protected function getFilterForType($type)
    {
        // If the filter manager has an according filter return it
        if ($this->filterManager->has($type)) {
            return $this->filterManager->get($type);
        }

        $filter = null;
        $filterBinaryPath = null;
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
            return null;
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
     * Handles filter exceptions
     *
     * @param \Assetic\Exception\FilterException $exception
     * @throws \Assetic\Exception\FilterException if run in CLI mode
     * @return string
     */
    protected function handleFilterException(FilterException $exception)
    {
        if ($this->isDevelopment()) {
            if (php_sapi_name() == 'cli') {
                throw $exception;
            }

            $i = 0;
            $code = '';
            $backtrace = $exception->getTrace();

            $heading = 'Caught Assetic error #' . $exception->getCode() . ': ' . $exception->getMessage();
            while ($step = current($backtrace)) {
                $code .= '#' . $i . ': ' . $step['file'] . '(' . $step['line'] . '): ';
                if (isset($step['class'])) {
                    $code .= $step['class'] . $step['type'];
                }
                $code .= $step['function'] . '(arguments: ' . count($step['args']) . ')' . PHP_EOL;
                next($backtrace);
                $i++;
            }
            $styles = array(
                'width'           => '100%',
                'overflow'        => 'scroll',
                'border'          => '1px solid #777',
                'background'      => '#ccc',
                'padding'         => '5px',
                '-moz-box-sizing' => 'border-box',
                'box-sizing'      => 'border-box',
                'box-shadow'      => 'inset 0 0 4px rgba(0, 0, 0, 0.3)',
                'font-family'     => 'sans-serif',
            );
            array_walk($styles, function (&$value, $key) {
                $value = $key . ':' . $value;
            });
            $style = implode(';', $styles);

            echo '<div style="' . $style . '">' . $heading . PHP_EOL . '<pre>' . $code . '</pre></div>';
        } else {
            if (defined('TYPO3_DLOG') && TYPO3_DLOG) {
                $code = 'Caught exception #' . $exception->getCode() . ': ' . $exception->getMessage();
                GeneralUtility::devLog($code, 'assetic');
            }
        }
        return '';
    }


    // MWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWM
    // HELPERS
    // MWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWM
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
     * Returns the "options" configuration from the TypoScript of the current page
     *
     * @return array
     */
    public function getPluginLevelOptions()
    {
        return $this->pluginLevelOptions;
    }

    /**
     * Injects the "options" configuration from the TypoScript of the current page
     *
     * @param array $pluginLevelOptions
     */
    public function setPluginLevelOptions($pluginLevelOptions)
    {
        $this->pluginLevelOptions = $pluginLevelOptions;
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
     * Returns the shared asset manager
     *
     * @return \Assetic\AssetManager
     */
    public function getAssetManager()
    {
        if (!$this->assetManager) {
            $this->assetManager = new AssetManager();
        }
        return $this->assetManager;
    }

    /**
     * Invokes the functions of the filter
     *
     * @param  Filter\FilterInterface $filter                  The filter to apply to
     * @param  array                  $stylesheetConfiguration The stylesheet configuration
     * @param  string                 $stylesheetType          The stylesheet type
     * @throws \UnexpectedValueException if the given stylesheet type is invalid
     * @return Filter\FilterInterface                            Returns the filter
     */
    protected function applyFunctionsToFilterForType($filter, $stylesheetConfiguration, $stylesheetType)
    {
        if (!$stylesheetType) {
            throw new \UnexpectedValueException('The given stylesheet type is invalid "' . $stylesheetType . '"',
                1355910725);
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

            AsseticGeneralUtility::pd("Call function $function on filter", $filter, $data);
            if (is_callable(array($filter, $function))) {
                call_user_func_array(array($filter, $function), $data);
            } else {
                trigger_error('Filter does not implement ' . $function, E_USER_NOTICE);
            }
        }

        AsseticGeneralUtility::pd($filter);
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
    protected function prepareFunctionParameters(&$parameters)
    {
        foreach ($parameters as &$parameter) {
            if (strpos($parameter, '.') !== false || strpos($parameter, DIRECTORY_SEPARATOR) !== false) {
                $parameter = GeneralUtility::getFileAbsFileName($parameter);
            }
        }
    }
}