<?php


namespace Cundd\Assetic\Compiler;

use Assetic\Asset\AssetCollection;
use Assetic\AssetManager;
use Assetic\AssetWriter;
use Assetic\Exception\FilterException;
use Assetic\Factory\AssetFactory;
use Assetic\Filter;
use Assetic\FilterManager;
use Cundd\Assetic\Exception\FilePathException;
use Cundd\Assetic\Utility\ConfigurationUtility;
use Cundd\Assetic\Utility\ExceptionPrinter;
use Cundd\Assetic\Utility\GeneralUtility as AsseticGeneralUtility;
use Exception as Exception;
use LogicException;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Log\LogManager;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Compiler
 *
 * The class that builds the connection between Assetic and TYPO3
 */
class Compiler implements CompilerInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;

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
    protected $configuration = [];

    /**
     * @var array
     */
    protected $pluginLevelOptions = [];

    /**
     * @var LoggerInterface
     */
    protected $logger;

    public function __construct($configuration)
    {
        $this->configuration = $configuration;
    }

    /**
     * Collects all the assets and adds them to the asset manager
     *
     * @return AssetCollection
     * @throws LogicException if the assetic classes could not be found
     */
    public function collectAssets()
    {
        $this->logException(new Exception('Ff'));
        AsseticGeneralUtility::profile('Will collect assets');
        $pathToWeb = ConfigurationUtility::getPathToWeb();

        // Check if the Assetic classes are available
        if (!class_exists(AssetCollection::class, true)) {
            throw new LogicException('The Assetic classes could not be found', 1356543545);
        }
        $assetManager = $this->getAssetManager();
        $assetCollection = new AssetCollection();
        $factory = new AssetFactory($pathToWeb);
        $this->filterManager = new FilterManager();

        // Register the filter manager
        $factory->setFilterManager($this->filterManager);

        // Loop through all configured stylesheets
        $stylesheets = isset($this->configuration['stylesheets.']) ? $this->configuration['stylesheets.'] : [];
        foreach ($stylesheets as $assetKey => $stylesheet) {
            if (!is_array($stylesheet)) {
                $this->createAsset($assetKey, $stylesheet, $assetCollection, $factory);
            }
        }

        // Set the output file name
        $assetManager->set('cundd_assetic', $assetCollection);
        AsseticGeneralUtility::profile('Did collect assets');

        return $assetCollection;
    }

    /**
     * Collects the files and tells assetic to compile the files
     *
     * @return bool Returns if the files have been compiled successfully
     * @throws Exception if an exception is thrown during rendering
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
        } catch (Exception $exception) {
            if ($this->isDevelopment()) {
                if (is_a($exception, 'Exception_ScssException')) {
                    /** @var \Exception_ScssException $exception */
                    AsseticGeneralUtility::pd($exception->getUserInfo());
                }

                throw $exception;
            } else {
                $this->logException($exception);
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
     * @param string $type The file type
     * @return Filter\FilterInterface       The filter
     * @throws LogicException if the required filter class does not exist
     */
    protected function getFilterForType($type)
    {
        // If the filter manager has an according filter return it
        if ($this->filterManager->has($type)) {
            return $this->filterManager->get($type);
        }

        $filter = null;
        $filterClass = ucfirst($type) . 'Filter';
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

        if (class_exists($filterClass)) {
            $filterBinaryPath = $this->getFilterBinaryPath($filterClass);
            if ($filterBinaryPath) {
                $filter = new $filterClass($filterBinaryPath);
            } else {
                $filter = new $filterClass();
            }
        } else {
            throw new FilterException('Filter class ' . $filterClass . ' not found', 1355846301);
        }

        // Store the just created filter
        $this->filterManager->set($type, $filter);

        return $filter;
    }

    /**
     * Handles filter exceptions
     *
     * @param \Assetic\Exception\FilterException $exception
     * @return string
     * @throws \Assetic\Exception\FilterException if run in CLI mode
     */
    protected function handleFilterException(FilterException $exception)
    {
        if ($this->isDevelopment()) {
            if (php_sapi_name() == 'cli') {
                throw $exception;
            }
            $exceptionPrinter = new ExceptionPrinter();
            $exceptionPrinter->printException($exception);
        } else {
            $this->logException($exception);
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
        return ConfigurationUtility::isDevelopment($this->configuration);
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
     * @return bool
     */
    private function isStrict()
    {
        return isset($this->configuration['strict']) && $this->configuration['strict'];
    }

    /**
     * Invokes the functions of the filter
     *
     * @param Filter\FilterInterface $filter                  The filter to apply to
     * @param array                  $stylesheetConfiguration The stylesheet configuration
     * @param string                 $stylesheetType          The stylesheet type
     * @return Filter\FilterInterface                            Returns the filter
     * @throws \UnexpectedValueException if the given stylesheet type is invalid
     */
    protected function applyFunctionsToFilterForType($filter, $stylesheetConfiguration, $stylesheetType)
    {
        if (!$stylesheetType) {
            throw new \UnexpectedValueException(
                'The given stylesheet type is invalid "' . $stylesheetType . '"',
                1355910725
            );
        }
        $functions = $stylesheetConfiguration['functions.'];
        ksort($functions);
        foreach ($functions as $function => $data) {
            if (!is_array($data)) {
                $data = [$data];
            }
            $this->prepareFunctionParameters($data);

            // Check if the function has a numerator as prefix strip that off
            if ($function[1] === '-' && is_numeric($function[0])) {
                $function = substr($function, 2);
            }


            AsseticGeneralUtility::pd("Call function $function on filter", $filter, $data);
            if (is_callable([$filter, $function])) {
                call_user_func_array([$filter, $function], $data);
            } elseif ($this->isStrict()) {
                throw new FilterException(
                    sprintf('Filter "%s" does not implement method "%s"', get_class($filter), $function),
                    1447161985
                );
            } else {
                trigger_error('Filter does not implement ' . $function, E_USER_NOTICE);
            }
        }

        AsseticGeneralUtility::pd($filter);
        $this->filterManager->set($stylesheetType, $filter);

        return $filter;
    }

    /**
     * Create and collect the Asset with the given key and stylesheet
     *
     * @param string          $assetKey
     * @param string          $stylesheet
     * @param AssetCollection $assetCollection
     * @param AssetFactory    $factory
     * @return AssetCollection|null
     */
    public function createAsset($assetKey, $stylesheet, AssetCollection $assetCollection, AssetFactory $factory)
    {
        $pluginLevelOptions = $this->getPluginLevelOptions();

        $stylesheetConf = is_array(
            $this->configuration['stylesheets.'][$assetKey . '.']
        ) ? $this->configuration['stylesheets.'][$assetKey . '.'] : [];

        // Get the type to find the according filter
        if (isset($stylesheetConf['type'])) {
            $stylesheetType = $stylesheetConf['type'] . '';
        } else {
            $stylesheetType = substr(strrchr($stylesheet, '.'), 1);
        }

        $originalStylesheet = $stylesheet;
        $stylesheet = GeneralUtility::getFileAbsFileName($stylesheet);
        if (!$stylesheet) {
            throw new FilePathException(
                sprintf('Could not determine absolute path for asset file "%s"', $originalStylesheet)
            );
        }

        // Make sure the filter manager knows the filter
        $filter = $this->getFilterForType($stylesheetType);
        if ($filter) {
            $assetFilters = [$stylesheetType];
        } else {
            $assetFilters = [];
        }

        // Check if there are filter functions
        if (isset($stylesheetConf['functions.'])) {
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
            [$stylesheet],
            $assetFilters,
            $currentOptions
        );
        $assetCollection->add($asset);

        return $asset;
    }

    /**
     * Prepares the data to be passed to a filter function.
     *
     * I.e. expands paths to their absolute path.
     *
     * @param array $parameters Reference to the data array
     * @return void
     */
    protected function prepareFunctionParameters(&$parameters)
    {
        foreach ($parameters as &$parameter) {
            if (strpos($parameter, '.') !== false || strpos($parameter, DIRECTORY_SEPARATOR) !== false) {
                $path = GeneralUtility::getFileAbsFileName($parameter);
                $parameter = realpath($path) ?: $path;
            }
        }
    }

    /**
     * @param string $filterClass
     * @return string
     */
    private function getFilterBinaryPath(string $filterClass)
    {
        $filterBinaryPath = null;
        $filterBinaries = $this->configuration['filter_binaries.'];

        // Replace the backslash in the filter class with an underscore
        $filterClassIdentifier = strtolower(str_replace('\\', '_', $filterClass));
        if (!isset($filterBinaries[$filterClassIdentifier])) {
            return null;
        }

        $filterBinaryPath = $filterBinaries[$filterClassIdentifier];
        if (!is_string($filterBinaryPath)) {
            // @TODO: Check if this can/should happen
            return $filterBinaryPath;
        }

        if ($filterBinaryPath[0] === '~') {
            $homeDirectory = $this->getHomeDirectory();
            $filterBinaryPath = $homeDirectory . substr($filterBinaryPath, 1);
        } elseif (substr($filterBinaryPath, 0, 4) === 'EXT:') {
            $filterBinaryPath = GeneralUtility::getFileAbsFileName($filterBinaryPath);
        }

        return $filterBinaryPath;
    }

    /**
     * @return string
     */
    private function getHomeDirectory()
    {
        $homeDirectory = getenv('HOME');
        if ($homeDirectory) {
            return $homeDirectory;
        }
        if (isset($_SERVER['HOME'])) {
            return $_SERVER['HOME'];
        }

        return isset($_SERVER['DOCUMENT_ROOT']) ? $_SERVER['DOCUMENT_ROOT'] . '/..' : '';
    }

    /**
     * @param Exception $exception
     */
    private function logException(Exception $exception)
    {
        if (!$this->logger) {
            $this->logger = GeneralUtility::makeInstance(LogManager::class)->getLogger(__CLASS__);
        }
        $this->logger->error('Caught exception #' . $exception->getCode() . ': ' . $exception->getMessage());
    }
}
