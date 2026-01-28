<?php

declare(strict_types=1);

namespace Cundd\Assetic\Configuration;

use Cundd\Assetic\Configuration;
use Cundd\Assetic\Exception\ConfigurationException;
use Cundd\Assetic\Exception\InvalidConfigurationException;
use Cundd\Assetic\Exception\MissingConfigurationException;
use Cundd\Assetic\ValueObject\CompilationContext;
use Cundd\Assetic\ValueObject\Result;
use Cundd\Assetic\ValueObject\Result\Err;
use Cundd\Assetic\ValueObject\Result\Ok;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Site\Entity\SiteSettings;

/**
 * @phpstan-import-type FilterArgument from StylesheetConfiguration
 *
 * @phpstan-type RawStylesheetFunctions array<non-empty-string,FilterArgument>
 * @phpstan-type RawStylesheetConfiguration array{
 *      file:non-empty-string,
 *      type:non-empty-string|null,
 *      functions:array<non-empty-string,FilterArgument>
 *  }
 * @phpstan-type RawFilterBinaries array<non-empty-string, non-empty-string>
 * @phpstan-type RawFilterForType array<non-empty-string, class-string|'none'>
 */
class ConfigurationFactory
{
    /**
     * @return Result<Configuration,covariant ConfigurationException>
     */
    public function buildFromRequest(
        ServerRequestInterface $request,
        CompilationContext $compilationContext,
    ): Result {
        return $this->buildFromCompilationContext($compilationContext);
    }

    /**
     * @return Result<Configuration,covariant ConfigurationException>
     */
    public function buildFromCli(
        CompilationContext $compilationContext,
    ): Result {
        return $this->buildFromCompilationContext($compilationContext);
    }

    /**
     * @return Result<Configuration,covariant ConfigurationException>
     */
    private function buildFromCompilationContext(
        CompilationContext $compilationContext,
    ): Result {
        $settings = $compilationContext->site->getSettings();
        $isDevelopment = $compilationContext->isCliEnvironment
            || $settings->get('assetic.settings.development', false);

        $liveReloadConfiguration = new LiveReloadConfiguration(
            isEnabled: (bool) $settings->get(
                'assetic.settings.livereload.addJavascript',
                false
            ),
            port: (int) $settings->get(
                'assetic.settings.livereload.port',
                35729
            ),
            skipServerTest: (bool) $settings->get(
                'assetic.settings.livereload.skipServerTest',
                false
            )
        );

        $createSymlink = $liveReloadConfiguration->isEnabled
            || $settings->get(
                'assetic.settings.createSymlink',
                false
            );
        $allowCompileWithoutLogin = $settings->get(
            'assetic.settings.allowCompileWithoutLogin',
            false
        );
        $strictModeEnabled = (bool) $settings->get('assetic.settings.strict');

        $asseticConfigurationResult = $this->getValidatedStylesheetsAndSettings($settings);
        if ($asseticConfigurationResult->isErr()) {
            return new Err($asseticConfigurationResult->unwrapErr());
        }
        $asseticConfiguration = $asseticConfigurationResult->unwrap();

        $stylesheetConfigurations = $this->buildStylesheetConfigurations(
            $asseticConfiguration['stylesheets']
        );

        $allSettings = $asseticConfiguration['settings'];

        return new Ok(
            new Configuration(
                stylesheetConfigurations: $stylesheetConfigurations,
                allowCompileWithoutLogin: $allowCompileWithoutLogin,
                outputFileDir: Configuration::OUTPUT_FILE_DIR,
                outputFileName: $settings->get('assetic.settings.output', null),
                isDevelopment: $isDevelopment,
                liveReloadConfiguration: $liveReloadConfiguration,
                createSymlink: $createSymlink,
                filterForType: $allSettings['filterForType'],
                filterBinaries: $allSettings['filterBinaries'],
                strictModeEnabled: $strictModeEnabled,
                site: $compilationContext->site
            )
        );
    }

    /**
     * @param RawStylesheetConfiguration[] $stylesheets
     *
     * @return StylesheetConfiguration[]
     */
    private function buildStylesheetConfigurations(array $stylesheets): array
    {
        return array_map(
            fn (array $stylesheet) => new StylesheetConfiguration(
                $stylesheet['file'],
                $stylesheet['functions'],
                $stylesheet['type'],
            ),
            $stylesheets
        );
    }

    /**
     * @return Result<array{
     *      stylesheets:RawStylesheetConfiguration[],
     *      settings:array{
     *          filterBinaries:RawFilterBinaries,
     *          filterForType:RawFilterForType,
     *      }
     * }, covariant ConfigurationException>
     */
    private function getValidatedStylesheetsAndSettings(SiteSettings $settings): Result
    {
        $fullConfiguration = $settings->getAll();
        if (!is_array($fullConfiguration['assetic'] ?? false)
            || empty($fullConfiguration['assetic'])) {
            return (new MissingConfigurationException(
                'Assetic is not configured properly',
                1769439993
            ))->intoErr();
        }

        $asseticConfiguration = $fullConfiguration['assetic'];
        if (!is_array($asseticConfiguration['settings'] ?? false)
            || empty($asseticConfiguration['settings'])) {
            return (new MissingConfigurationException(
                'No assetic settings are defined',
                1769440089
            ))->intoErr();
        }

        $stylesheetsResult = $this->getValidatedRawStylesheetConfiguration(
            $asseticConfiguration
        );
        if ($stylesheetsResult->isErr()) {
            return new Err($stylesheetsResult->unwrapErr());
        }

        $settings = $asseticConfiguration['settings'];
        $filterBinariesResult = $this->getValidatedFilterBinaries($settings);
        if ($filterBinariesResult->isErr()) {
            return new Err($filterBinariesResult->unwrapErr());
        }
        $filterForTypeResult = $this->getValidatedFilterForType($settings);
        if ($filterForTypeResult->isErr()) {
            return new Err($filterForTypeResult->unwrapErr());
        }

        // @phpstan-ignore return.type
        return new Ok([
            'stylesheets' => $stylesheetsResult->unwrap(),
            'settings'    => [
                'filterBinaries' => $filterBinariesResult->unwrap(),
                'filterForType'  => $filterForTypeResult->unwrap(),
            ],
        ]);
    }

    /**
     * @param array<string,mixed> $asseticConfiguration
     *
     * @return Result<array<RawStylesheetConfiguration>, covariant ConfigurationException>
     */
    private function getValidatedRawStylesheetConfiguration(
        array $asseticConfiguration,
    ): Result {
        $rawStylesheets = $asseticConfiguration['stylesheets'] ?? false;
        if (!is_array($rawStylesheets)) {
            return (new MissingConfigurationException(
                'No assets have been defined',
                1769437949
            ))->intoErr();
        }

        $validatedStylesheets = [];
        foreach ($rawStylesheets as $stylesheet) {
            $file = $stylesheet['file'] ?? null;

            if (!is_string($file) || '' === trim($file)) {
                return (new InvalidConfigurationException(
                    sprintf(
                        'Stylesheet file path must be a non-empty string. %s given',
                        get_debug_type($file)
                    )
                ))->intoErr();
            }

            $type = $stylesheet['type'] ?? null;
            if (null !== $type && false === (is_string($type) && '' !== $type)) {
                return (new InvalidConfigurationException(
                    sprintf(
                        'Stylesheet configuration `type` must be NULL or a non-empty string. %s given',
                        get_debug_type($type)
                    )
                ))->intoErr();
            }

            $validatedFunctionsResult = $this->getValidatedStylesheetFunctions(
                $stylesheet['functions'] ?? []
            );
            if ($validatedFunctionsResult->isErr()) {
                return $validatedFunctionsResult->unwrapErr()->intoErr();
            }

            $validatedStylesheets[] = [
                'file'      => $file,
                'type'      => $type,
                'functions' => $validatedFunctionsResult->unwrap(),
            ];
        }

        // @phpstan-ignore return.type
        return new Ok($validatedStylesheets);
    }

    /**
     * @param array<mixed,mixed> $rawFunctions
     *
     * @return Result<RawStylesheetFunctions, covariant ConfigurationException>
     */
    private function getValidatedStylesheetFunctions(
        array $rawFunctions,
    ): Result {
        $validatedFunctions = [];
        foreach ($rawFunctions as $functionName => $argument) {
            if (!is_string($functionName) || '' === trim($functionName)) {
                return (new InvalidConfigurationException(
                    sprintf(
                        'Name of filter-function must be a non-empty string. %s given',
                        get_debug_type($argument)
                    )
                ))->intoErr();
            }
            if (!is_string($argument)
                && !is_int($argument)
                && !is_bool($argument)
                && !is_float($argument)
            ) {
                return (new InvalidConfigurationException(
                    sprintf(
                        'Argument of filter-function must be a string. %s given',
                        get_debug_type($argument)
                    )
                ))->intoErr();
            }
            $validatedFunctions[$functionName] = $argument;
        }

        return new Ok($validatedFunctions);
    }

    /**
     * @param array<mixed,mixed> $settings
     *
     * @return Result<RawFilterForType, covariant ConfigurationException>
     */
    private function getValidatedFilterForType(
        array $settings,
    ): Result {
        $rawFilterForType = $settings['filterForType'] ?? null;

        // `filterForType` must be either NULL or an array
        if (null === $rawFilterForType) {
            // @phpstan-ignore return.type
            return new Ok([]);
        }

        if (!is_array($rawFilterForType)) {
            return (new InvalidConfigurationException(
                sprintf(
                    'Invalid configuration for `filterForType`. Expected type array got %s',
                    get_debug_type($rawFilterForType)
                ),
                1769440168
            ))->intoErr();
        }
        $validatedFilterForType = [];
        foreach ($rawFilterForType as $fileType => $filterClass) {
            if (!is_string($fileType) || '' === $fileType) {
                return (new InvalidConfigurationException(
                    sprintf(
                        'Invalid configuration for `filterForType` `fileType`. Expected non-empty-string got %s',
                        get_debug_type($fileType)
                    ),
                    1769590007
                ))->intoErr();
            }

            if ('none' === $filterClass) {
                // noop
            } elseif (!is_string($filterClass) || !class_exists($filterClass)) {
                $filterClassInfo = is_string($filterClass)
                    ? $filterClass
                    : get_debug_type($filterClass);

                return (new InvalidConfigurationException(
                    sprintf(
                        'Invalid configuration for `filterForType` `filterClass`. Expected class name or \'none\' got %s',
                        $filterClassInfo
                    ),
                    1769590000
                ))->intoErr();
            }
            $validatedFilterForType[$fileType] = $filterClass;
        }

        return new Ok($validatedFilterForType);
    }

    /**
     * @param array<mixed,mixed> $settings
     *
     * @return Result<RawFilterBinaries, covariant ConfigurationException>
     */
    private function getValidatedFilterBinaries(
        array $settings,
    ): Result {
        $rawFilterBinaries = $settings['filterBinaries'] ?? null;

        // `filterBinaries` must be either NULL or an array
        if (null === $rawFilterBinaries) {
            // @phpstan-ignore return.type
            return new Ok([]);
        }

        if (!is_array($rawFilterBinaries)) {
            return (new InvalidConfigurationException(
                sprintf(
                    'Invalid configuration for `filterBinaries`. Expected type array got %s',
                    get_debug_type($rawFilterBinaries)
                ),
                1769440169
            ))->intoErr();
        }

        $validatedFilterBinaries = [];
        foreach ($rawFilterBinaries as $fileType => $filterClass) {
            if (!is_string($fileType) || '' === $fileType) {
                return (new InvalidConfigurationException(
                    sprintf(
                        'Invalid configuration for `filterBinaries` `filterType`. Expected non-empty-string got %s',
                        get_debug_type($fileType)
                    ),
                    1769589833
                ))->intoErr();
            }
            if (!is_string($filterClass) || '' === $filterClass) {
                return (new InvalidConfigurationException(
                    sprintf(
                        'Invalid configuration for `filterBinaries` `binary`. Expected non-empty-string got %s',
                        get_debug_type($filterClass)
                    ),
                    1769590004
                ))->intoErr();
            }
            $validatedFilterBinaries[$fileType] = $filterClass;
        }

        return new Ok($validatedFilterBinaries);
    }
}
