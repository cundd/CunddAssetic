<?php

declare(strict_types=1);

namespace Cundd\Assetic\Compiler;

use Assetic\Asset\AssetCollection;
use Assetic\AssetManager;
use Assetic\AssetWriter;
use Assetic\Exception\FilterException;
use Assetic\Factory\AssetFactory;
use Assetic\Filter;
use Assetic\FilterManager;
use Cundd\Assetic\Configuration\ConfigurationProviderInterface;
use Cundd\Assetic\Exception\FilePathException;
use Cundd\Assetic\Utility\GeneralUtility as AsseticGeneralUtility;
use Cundd\Assetic\Utility\PathUtility;
use Cundd\Assetic\ValueObject\Result;
use LogicException;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Throwable;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use UnexpectedValueException;

use function get_class;
use function preg_match;

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
     */
    protected AssetManager $assetManager;

    /**
     * Assetic filter manager
     */
    protected FilterManager $filterManager;

    /**
     * Configuration from TYPO3
     *
     * @var array
     */
    private $configuration = [];

    /**
     * @var array
     */
    protected $pluginLevelOptions = [];

    /**
     * @param array<string,mixed> $pluginLevelOptions
     */
    public function __construct(
        private readonly ConfigurationProviderInterface $configurationProvider,
        array $pluginLevelOptions,
    ) {
        $this->pluginLevelOptions = $pluginLevelOptions;
    }

    /**
     * Collect all the assets and add them to the Asset Manager
     *
     * @throws LogicException if the Assetic classes could not be found
     */
    public function collectAssets(): AssetCollection
    {
        AsseticGeneralUtility::profile('Will collect assets');
        $pathToWeb = $this->configurationProvider->getPublicPath();

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
        $stylesheets = $this->configurationProvider->getStylesheetConfigurations();
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

    public function compile(): Result
    {
        $outputDirectory = $this->configurationProvider->getAbsoluteOutputFileDir();
        GeneralUtility::mkdir($outputDirectory);

        $writer = new AssetWriter($outputDirectory);

        AsseticGeneralUtility::profile('Will compile asset');
        try {
            $writer->writeManagerAssets($this->getAssetManager());
        } catch (Throwable $exception) {
            return new Result\Err($exception);
        }

        AsseticGeneralUtility::profile('Did compile asset');

        return new Result\Ok(null);
    }

    // =========================================================================
    // FILTERS
    // =========================================================================
    /**
     * Return the right filter for the given file type
     *
     * @param string $type The file type
     *
     * @return Filter\FilterInterface The filter
     *
     * @throws LogicException if the required filter class does not exist
     */
    protected function getFilterForType(string $type): ?Filter\FilterInterface
    {
        // If the filter manager has an according filter return it
        if ($this->filterManager->has($type)) {
            return $this->filterManager->get($type);
        }

        $filterClass = ucfirst($type) . 'Filter';
        $filterForTypeDefinitions = $this->configurationProvider->getFilterForType();

        // Check which filter should be used for the given type. This allows the
        // user i.e. to use lessphp for LESS files.
        if (isset($filterForTypeDefinitions[$type])) {
            $filterClass = $filterForTypeDefinitions[$type];
        }

        // Check if no filter should be used
        if ('none' === $filterClass) {
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

    // =========================================================================
    // HELPERS
    // =========================================================================
    /**
     * Return the shared asset manager
     */
    public function getAssetManager(): AssetManager
    {
        return $this->assetManager ??= new AssetManager();
    }

    /**
     * Invoke the functions of the filter
     *
     * @param Filter\FilterInterface $filter                  The filter to apply to
     * @param array                  $stylesheetConfiguration The stylesheet configuration
     * @param string                 $stylesheetType          The stylesheet type
     *
     * @return Filter\FilterInterface Return the filter
     *
     * @throws UnexpectedValueException if the given stylesheet type is invalid
     */
    protected function applyFunctionsToFilterForType(
        Filter\FilterInterface $filter,
        array $stylesheetConfiguration,
        string $stylesheetType,
    ): Filter\FilterInterface {
        if (!$stylesheetType) {
            throw new UnexpectedValueException('The given stylesheet type is invalid "' . $stylesheetType . '"', 1355910725);
        }
        $functions = $stylesheetConfiguration['functions.'];
        ksort($functions);
        foreach ($functions as $function => $data) {
            if (!is_array($data)) {
                $data = [$data];
            }
            $this->prepareFunctionParameters($data);

            // Check if the function has a numerator as prefix and strip that off
            if (preg_match('/^\d+-(\w+)/', $function, $matches)) {
                $function = $matches[1];
            }

            AsseticGeneralUtility::pd("Call function $function on filter", $filter, $data);
            if (is_callable([$filter, $function])) {
                call_user_func_array([$filter, $function], $data);
            } elseif ($this->configurationProvider->getStrictModeEnabled()) {
                throw new FilterException(sprintf('Filter "%s" does not implement method "%s"', get_class($filter), $function), 1447161985);
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
     */
    public function createAsset(
        string $assetKey,
        string $stylesheet,
        AssetCollection $assetCollection,
        AssetFactory $factory,
    ): ?AssetCollection {
        $allStylesheetConfiguration = $this->configurationProvider->getStylesheetConfigurations();
        $stylesheetConf = is_array($allStylesheetConfiguration[$assetKey . '.'])
            ? $allStylesheetConfiguration[$assetKey . '.']
            : [];

        // Get the type to find the according filter
        if (isset($stylesheetConf['type'])) {
            $stylesheetType = (string) $stylesheetConf['type'];
        } else {
            $stylesheetType = substr(strrchr($stylesheet, '.'), 1);
        }

        $originalStylesheet = $stylesheet;
        $stylesheet = PathUtility::getAbsolutePath($stylesheet);
        if (!$stylesheet) {
            throw new FilePathException(sprintf('Could not determine absolute path for asset file "%s"', $originalStylesheet), 8511589451);
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
            $currentOptions = $this->pluginLevelOptions;
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
     * Prepare the data to be passed to a filter function
     *
     * I.e. expands paths to their absolute path
     *
     * @param array $parameters Reference to the data array
     */
    protected function prepareFunctionParameters(array &$parameters): void
    {
        foreach ($parameters as &$parameter) {
            if (false !== strpos($parameter, '.') || false !== strpos($parameter, DIRECTORY_SEPARATOR)) {
                $path = PathUtility::getAbsolutePath($parameter);
                $parameter = realpath($path) ?: $path;
            }
        }
    }

    private function getFilterBinaryPath(string $filterClass): ?string
    {
        $filterBinaries = $this->configurationProvider->getFilterBinaries();

        // Replace the backslash in the filter class with an underscore
        $filterClassIdentifier = strtolower(str_replace('\\', '_', $filterClass));
        if (!isset($filterBinaries[$filterClassIdentifier])) {
            return null;
        }

        $filterBinaryPath = $filterBinaries[$filterClassIdentifier];
        if ('~' === $filterBinaryPath[0]) {
            $homeDirectory = $this->getHomeDirectory();
            $filterBinaryPath = $homeDirectory . substr($filterBinaryPath, 1);
        } elseif ('EXT:' === substr($filterBinaryPath, 0, 4)) {
            $filterBinaryPath = PathUtility::getAbsolutePath($filterBinaryPath);
        }

        return $filterBinaryPath;
    }

    private function getHomeDirectory(): string
    {
        $homeDirectory = getenv('HOME');
        if ($homeDirectory) {
            return (string) $homeDirectory;
        }
        if (isset($_SERVER['HOME'])) {
            return (string) $_SERVER['HOME'];
        }

        return isset($_SERVER['DOCUMENT_ROOT']) ? $_SERVER['DOCUMENT_ROOT'] . '/..' : '';
    }
}
