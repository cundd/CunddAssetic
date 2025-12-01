<?php

declare(strict_types=1);

namespace Cundd\Assetic\Configuration;

use Cundd\Assetic\Configuration;
use Cundd\Assetic\ValueObject\CompilationContext;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\TypoScript\FrontendTypoScript;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManager;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;
use UnexpectedValueException;

class ConfigurationFactory
{
    public function buildFromRequest(
        ServerRequestInterface $request,
        CompilationContext $compilationContext,
    ): Configuration {
        $setupArray = $this->getTypoScriptSetupForRequest($request);

        return $this->buildConfigurationFromArray($compilationContext, $setupArray);
    }

    public function buildFromCli(
        CompilationContext $compilationContext,
    ): Configuration {
        $simulateBackend = false;
        if (!isset($GLOBALS['TYPO3_REQUEST'])) {
            $simulateBackend = true;

            /* @see \TYPO3\CMS\Core\Core\SystemEnvironmentBuilder::REQUESTTYPE_BE */
            $GLOBALS['TYPO3_REQUEST'] = (new ServerRequest())
                ->withAttribute('applicationType', 2);
        }

        try {
            $configuration = $this->getTypoScriptSetupForBackendContext();

            return $this->buildConfigurationFromArray(
                $compilationContext,
                $configuration
            );
        } catch (UnexpectedValueException $exception) {
            throw $exception;
        } finally {
            if ($simulateBackend) {
                unset($GLOBALS['TYPO3_REQUEST']);
            }
        }
    }

    /**
     * @param array<string, mixed> $configuration
     */
    private function buildConfigurationFromArray(
        CompilationContext $compilationContext,
        array $configuration,
    ): Configuration {
        $isDevelopment = $compilationContext->isCliEnvironment || ($configuration['development'] ?? false);

        $rawLiveReloadConfiguration = $configuration['livereload.'] ?? [];
        $liveReloadConfiguration = new LiveReloadConfiguration(
            isEnabled: (bool) ($rawLiveReloadConfiguration['add_javascript'] ?? false),
            port: (int) ($rawLiveReloadConfiguration['port'] ?? 35729),
            skipServerTest: (bool) ($rawLiveReloadConfiguration['skip_server_test'] ?? false)
        );

        $createSymlink = ($configuration['create_symlink'] ?? false)
            || $liveReloadConfiguration->isEnabled;

        $allowCompileWithoutLogin = (bool) ($configuration['allow_compile_without_login'] ?? false);
        $stylesheetConfigurations = (array) ($configuration['stylesheets.'] ?? []);

        return new Configuration(
            stylesheetConfigurations: $stylesheetConfigurations,
            allowCompileWithoutLogin: $allowCompileWithoutLogin,
            outputFileDir: Configuration::OUTPUT_FILE_DIR,
            outputFileName: $configuration['output'] ?? null,
            isDevelopment: $isDevelopment,
            liveReloadConfiguration: $liveReloadConfiguration,
            createSymlink: $createSymlink,
            filterForType: (array) $configuration['filter_for_type.'],
            filterBinaries: (array) $configuration['filter_binaries.'],
            strictModeEnabled: (bool) ($configuration['strict'] ?? false),
            site: $compilationContext->site
        );
    }

    /**
     * @return array<string,mixed>
     */
    private function getTypoScriptSetupForRequest(
        ServerRequestInterface $request,
    ): array {
        $isFrontendRequest = 1 === $request->getAttribute('applicationType');
        if (!$isFrontendRequest) {
            return $this->getTypoScriptSetupForBackendContext();
        }

        /** @var FrontendTypoScript $frontendTypoScript */
        $frontendTypoScript = $request->getAttribute('frontend.typoscript');
        $allConfiguration = $frontendTypoScript->getSetupArray();
        if (isset($allConfiguration['plugin.']['CunddAssetic.'])) {
            return $allConfiguration['plugin.']['CunddAssetic.'];
        } else {
            throw new UnexpectedValueException(
                'Could not read configuration for "plugin.CunddAssetic"',
                2047991846
            );
        }
    }

    /**
     * @return array<string,mixed>
     */
    private function getTypoScriptSetupForBackendContext(): array
    {
        $configurationManager = GeneralUtility::makeInstance(ConfigurationManager::class);
        $allConfiguration = $configurationManager->getConfiguration(
            ConfigurationManagerInterface::CONFIGURATION_TYPE_FULL_TYPOSCRIPT
        );

        if (!isset($allConfiguration['plugin.']['CunddAssetic.'])) {
            throw new UnexpectedValueException(
                'Could not read backend configuration for "plugin.CunddAssetic"',
                2047991847
            );
        }

        return $allConfiguration['plugin.']['CunddAssetic.'];
    }
}
