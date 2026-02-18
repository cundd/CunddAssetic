<?php

declare(strict_types=1);

namespace Cundd\Assetic\Compiler;

use Assetic\Asset\AssetCollection;
use Assetic\Contracts\Filter\FilterInterface;
use Assetic\Exception\FilterException;
use Assetic\Factory\AssetFactory;
use Assetic\FilterManager;
use Cundd\Assetic\Configuration;
use Cundd\Assetic\Configuration\StylesheetConfiguration;
use Cundd\Assetic\Exception\FilePathException;
use Cundd\Assetic\Exception\InvalidConfigurationException;
use Cundd\Assetic\Utility\PathUtility;
use Cundd\Assetic\Utility\ProfilingUtility;
use LogicException;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use TYPO3\CMS\Core\Core\Environment;

use function get_class;
use function preg_match;

/**
 * Class to build `AssetInterface` instances for configured stylesheets
 *
 * @phpstan-import-type FilterArgument from StylesheetConfiguration
 */
final class AssetCollector implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    /**
     * Collect all the assets and add them to the Asset Manager
     *
     * @throws LogicException if the Assetic classes could not be found
     */
    public function collectAssets(Configuration $configuration): AssetCollection
    {
        ProfilingUtility::start('Will collect assets');

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
        foreach ($stylesheets as $stylesheet) {
            $asset = $this->createAsset(
                $filterManager,
                $configuration,
                $stylesheet,
                $factory
            );
            $assetCollection->add($asset);
        }

        ProfilingUtility::end('Did collect assets');

        return $assetCollection;
    }

    /**
     * Create and collect the Asset with the given key and stylesheet
     */
    private function createAsset(
        FilterManager $filterManager,
        Configuration $configuration,
        StylesheetConfiguration $stylesheetConfiguration,
        AssetFactory $factory,
    ): AssetCollection {
        // Get the type to find the matching filter
        $stylesheetType = $stylesheetConfiguration->type
            ?? substr((string) strrchr($stylesheetConfiguration->file, '.'), 1);

        if (!$stylesheetType) {
            throw new InvalidConfigurationException(
                sprintf(
                    'Could not determine the type for asset file "%s"',
                    $stylesheetConfiguration->file
                ),
                1769689461
            );
        }

        $filePath = PathUtility::getAbsolutePath($stylesheetConfiguration->file);
        if (!$filePath) {
            throw new FilePathException(
                sprintf(
                    'Could not determine absolute path for asset file "%s"',
                    $stylesheetConfiguration->file
                ),
                8511589451
            );
        }

        // Make sure the filter manager knows the filter
        $filter = $this->getFilterForType(
            $filterManager,
            $configuration,
            $stylesheetType
        );

        // Check if there are filter functions
        $functions = $stylesheetConfiguration->functions;
        if ($filter && !empty($functions)) {
            $this->applyFunctionsToFilterForType(
                $filterManager,
                $configuration,
                $filter,
                $functions,
                $stylesheetType
            );
        }
        if ($configuration->isDevelopment) {
            $functions = $stylesheetConfiguration->developmentFunctions;
            if ($filter && !empty($functions)) {
                $this->applyFunctionsToFilterForType(
                    $filterManager,
                    $configuration,
                    $filter,
                    $functions,
                    $stylesheetType
                );
            }
        }

        $assetFilters = $filter ? [$stylesheetType] : [];

        return $factory->createAsset(
            [$filePath],
            $assetFilters,
        );
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

        // Default to `CssFilter` or `SassFilter` depending on the input file
        // type
        $filterClass = ucfirst($type) . 'Filter';

        // Check which filter should be used for the given type. This allows the
        // user i.e. to use lessphp for LESS files.
        if (isset($configuration->filterForType[$type])) {
            $filterClass = $configuration->filterForType[$type];
        }

        // Check if no filter should be used
        if ('none' === $filterClass) {
            return null;
        }

        assert(class_exists($filterClass));
        $filterBinaryPath = $this->getFilterBinaryPath(
            $configuration,
            $filterClass
        );
        if ($filterBinaryPath) {
            $filter = new $filterClass($filterBinaryPath);
        } else {
            $filter = new $filterClass();
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
     * @param array<non-empty-string,FilterArgument> $functions
     * @param non-empty-string                       $stylesheetType
     */
    private function applyFunctionsToFilterForType(
        FilterManager $filterManager,
        Configuration $configuration,
        FilterInterface $filter,
        array $functions,
        string $stylesheetType,
    ): FilterInterface {
        ksort($functions);
        foreach ($functions as $function => $argument) {
            $data = [$argument];
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
                throw new FilterException(
                    sprintf(
                        'Filter "%s" does not implement method "%s"',
                        get_class($filter),
                        $function
                    ),
                    1447161985
                );
            } else {
                trigger_error('Filter does not implement ' . $function, E_USER_NOTICE);
            }
        }

        $filterManager->set($stylesheetType, $filter);

        return $filter;
    }

    /**
     * Prepare the data to be passed to a filter function
     *
     * I.e. expands paths to their absolute path
     *
     * @param array<string|int|float|bool> $parameters Reference to the data array
     */
    private function prepareFunctionParameters(array &$parameters): void
    {
        foreach ($parameters as &$parameter) {
            if (is_int($parameter)
                || is_bool($parameter)
                || is_float($parameter)) {
                continue;
            }
            if (str_contains($parameter, '.')
                || str_contains($parameter, DIRECTORY_SEPARATOR)) {
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
