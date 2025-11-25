<?php

declare(strict_types=1);

namespace Cundd\Assetic\Compiler;

use Assetic\Asset\AssetCollection;
use Assetic\AssetManager;
use Assetic\AssetWriter;
use Assetic\Contracts\Filter\FilterInterface;
use Assetic\Exception\FilterException;
use Assetic\Factory\AssetFactory;
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
     * @param array<string,mixed> $pluginLevelOptions
     */
    public function __construct(
        private readonly ConfigurationProviderInterface $configurationProvider,
        protected array $pluginLevelOptions,
    ) {
        $this->assetManager = new AssetManager();
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
        $this->assetManager->set('cundd_assetic', $assetCollection);
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
            $writer->writeManagerAssets($this->assetManager);
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
     * Return the  filter for the given file type
     *
     * @throws LogicException if the required filter class does not exist
     */
    protected function getFilterForType(string $type): ?FilterInterface
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

        assert($filter instanceof FilterInterface);

        // Store the just created filter
        $this->filterManager->set($type, $filter);

        return $filter;
    }

    // =========================================================================
    // HELPERS
    // =========================================================================
    /**
     * Return the shared asset manager
     *
     * @deprecated
     */
    public function getAssetManager(): AssetManager
    {
        return $this->assetManager;
    }

    /**
     * Invoke the functions of the filter
     *
     * @param array<non-empty-string,string|string[]> $functions
     *
     * @throws UnexpectedValueException if the given stylesheet type is invalid
     */
    protected function applyFunctionsToFilterForType(
        FilterInterface $filter,
        array $functions,
        string $stylesheetType,
    ): FilterInterface {
        if (!$stylesheetType) {
            throw new UnexpectedValueException('The given stylesheet type is invalid "' . $stylesheetType . '"', 1355910725);
        }
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
                // `call_user_func_array` does remove the `strict_types` check
                // Invoking `$filter->$function(...$data)` instead would require
                // the function parameters to be prepared beforehand
                call_user_func_array($filter->$function(...), array_values($data));
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
        if (isset($stylesheetConf['type']) && is_scalar($stylesheetConf['type'])) {
            $stylesheetType = (string) $stylesheetConf['type'];
        } else {
            $stylesheetType = substr((string) strrchr($stylesheet, '.'), 1);
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
        /** @var array<non-empty-string, string|string[]> $functions */
        $functions = $stylesheetConf['functions.'] ?? [];
        if ($filter && !empty($functions) && is_array($functions)) {
            $this->applyFunctionsToFilterForType($filter, $functions, $stylesheetType);
        }

        // Check if there are special options for this stylesheet
        if (isset($stylesheetConf['options.'])) {
            $currentOptions = $stylesheetConf['options.'];
        } else {
            $currentOptions = $this->pluginLevelOptions;
        }
        AsseticGeneralUtility::pd($currentOptions);

        assert(is_array($currentOptions));
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
     * @param array<string> $parameters Reference to the data array
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
            $homeDirectory = PathUtility::getHomeDirectory();
            $filterBinaryPath = $homeDirectory . substr($filterBinaryPath, 1);
        } elseif ('EXT:' === substr($filterBinaryPath, 0, 4)) {
            $filterBinaryPath = PathUtility::getAbsolutePath($filterBinaryPath);
        }

        return $filterBinaryPath;
    }
}
