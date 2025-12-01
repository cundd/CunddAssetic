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
use Cundd\Assetic\Configuration;
use Cundd\Assetic\Exception\FilePathException;
use Cundd\Assetic\Utility\PathUtility;
use Cundd\Assetic\Utility\ProfilingUtility;
use Cundd\Assetic\ValueObject\Result;
use LogicException;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Throwable;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use UnexpectedValueException;

use function get_class;
use function preg_match;

/**
 * Compiler
 *
 * The class that builds the connection between Assetic and TYPO3
 */
final class Compiler implements CompilerInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;

    /**
     * Assetic asset manager
     */
    private AssetManager $assetManager;

    public function __construct()
    {
        $this->assetManager = new AssetManager();
    }

    /**
     * Collect all the assets and add them to the Asset Manager
     *
     * @throws LogicException if the Assetic classes could not be found
     */
    public function collectAssets(Configuration $configuration): AssetCollection
    {
        ProfilingUtility::profile('Will collect assets');

        // Check if the Assetic classes are available
        if (!class_exists(AssetCollection::class, true)) {
            throw new LogicException(
                'The Assetic classes could not be found',
                1356543545
            );
        }

        $assetCollection = new AssetCollection();
        $factory = new AssetFactory(Environment::getPublicPath() . '/');
        $filterManager = new FilterManager();

        // Register the filter manager
        $factory->setFilterManager($filterManager);

        // Loop through all configured stylesheets
        $stylesheets = $configuration->stylesheetConfigurations;
        foreach ($stylesheets as $assetKey => $stylesheet) {
            if (!is_array($stylesheet)) {
                $this->createAsset(
                    $filterManager,
                    $configuration,
                    $assetKey,
                    $stylesheet,
                    $assetCollection,
                    $factory
                );
            }
        }

        // Set the output file name
        $this->assetManager->set('cundd_assetic', $assetCollection);
        ProfilingUtility::profile('Did collect assets');

        return $assetCollection;
    }

    public function compile(Configuration $configuration): Result
    {
        $outputDirectory = Environment::getPublicPath()
            . '/' . $configuration->outputFileDir;
        GeneralUtility::mkdir($outputDirectory);

        $writer = new AssetWriter($outputDirectory);

        ProfilingUtility::profile('Will compile asset');
        try {
            $writer->writeManagerAssets($this->assetManager);
        } catch (Throwable $exception) {
            return new Result\Err($exception);
        }

        ProfilingUtility::profile('Did compile asset');

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
    private function getFilterForType(
        FilterManager $filterManager,
        Configuration $configuration,
        string $type,
    ): ?FilterInterface {
        // If the filter manager has an according filter return it
        if ($filterManager->has($type)) {
            return $filterManager->get($type);
        }

        $filterClass = ucfirst($type) . 'Filter';
        $filterForTypeDefinitions = $configuration->filterForType;

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
            $filterBinaryPath = $this->getFilterBinaryPath(
                $configuration,
                $filterClass
            );
            if ($filterBinaryPath) {
                $filter = new $filterClass($filterBinaryPath);
            } else {
                $filter = new $filterClass();
            }
        } else {
            throw new FilterException(
                'Filter class ' . $filterClass . ' not found',
                1355846301
            );
        }

        assert($filter instanceof FilterInterface);

        // Store the just created filter
        $filterManager->set($type, $filter);

        return $filter;
    }

    // =========================================================================
    // HELPERS
    // =========================================================================
    /**
     * Invoke the functions of the filter
     *
     * @param array<non-empty-string,string|string[]> $functions
     *
     * @throws UnexpectedValueException if the given stylesheet type is invalid
     */
    private function applyFunctionsToFilterForType(
        FilterManager $filterManager,
        Configuration $configuration,
        FilterInterface $filter,
        array $functions,
        string $stylesheetType,
    ): FilterInterface {
        if (!$stylesheetType) {
            throw new UnexpectedValueException(
                'The given stylesheet type is invalid "' . $stylesheetType . '"',
                1355910725
            );
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

            $this->logger?->debug(
                "Call function `$function` on filter",
                ['filter' => $filter, 'data' => $data]
            );
            if (is_callable([$filter, $function])) {
                // `call_user_func_array` does remove the `strict_types` check
                // Invoking `$filter->$function(...$data)` instead would require
                // the function parameters to be prepared beforehand
                call_user_func_array($filter->$function(...), array_values($data));
            } elseif ($configuration->strictModeEnabled) {
                throw new FilterException(sprintf(
                    'Filter "%s" does not implement method "%s"',
                    get_class($filter),
                    $function
                ), 1447161985);
            } else {
                trigger_error('Filter does not implement ' . $function, E_USER_NOTICE);
            }
        }

        $filterManager->set($stylesheetType, $filter);

        return $filter;
    }

    /**
     * Create and collect the Asset with the given key and stylesheet
     */
    public function createAsset(
        FilterManager $filterManager,
        Configuration $configuration,
        string $assetKey,
        string $stylesheet,
        AssetCollection $assetCollection,
        AssetFactory $factory,
    ): AssetCollection {
        $allStylesheetConfiguration = $configuration->stylesheetConfigurations;
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
            throw new FilePathException(
                sprintf(
                    'Could not determine absolute path for asset file "%s"',
                    $originalStylesheet
                ),
                8511589451
            );
        }

        // Make sure the filter manager knows the filter
        $filter = $this->getFilterForType($filterManager, $configuration, $stylesheetType);
        if ($filter) {
            $assetFilters = [$stylesheetType];
        } else {
            $assetFilters = [];
        }

        // Check if there are filter functions
        /** @var array<non-empty-string, string|string[]> $functions */
        $functions = $stylesheetConf['functions.'] ?? [];
        if ($filter && !empty($functions) && is_array($functions)) {
            $this->applyFunctionsToFilterForType(
                $filterManager,
                $configuration,
                $filter,
                $functions,
                $stylesheetType
            );
        }

        // Check if there are special options for this stylesheet
        $currentOptions = $stylesheetConf['options.'] ?? [];
        $this->logger?->debug(
            'Use stylesheet configuration options',
            ['options' => $currentOptions]
        );
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
    private function prepareFunctionParameters(array &$parameters): void
    {
        foreach ($parameters as &$parameter) {
            if (false !== strpos($parameter, '.') || false !== strpos($parameter, DIRECTORY_SEPARATOR)) {
                $path = PathUtility::getAbsolutePath($parameter);
                $parameter = realpath($path) ?: $path;
            }
        }
    }

    private function getFilterBinaryPath(
        Configuration $configuration,
        string $filterClass,
    ): ?string {
        $filterBinaries = $configuration->filterBinaries;

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
