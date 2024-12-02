<?php

declare(strict_types=1);

namespace Cundd\Assetic\Configuration;

use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManager;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;
use UnexpectedValueException;

class ConfigurationProviderFactory
{
    public function build(): ConfigurationProviderInterface
    {
        $configurationManager = GeneralUtility::makeInstance(ConfigurationManager::class);

        // @TODO: Find a stable way to get the configuration
        $simulateBackend = false;
        if (!isset($GLOBALS['TYPO3_REQUEST'])) {
            $simulateBackend = true;

            /* @see TYPO3\CMS\Core\Core\SystemEnvironmentBuilder::REQUESTTYPE_BE */
            $GLOBALS['TYPO3_REQUEST'] = (new ServerRequest())->withAttribute('applicationType', 2);
        }

        $allConfiguration = $configurationManager->getConfiguration(
            ConfigurationManagerInterface::CONFIGURATION_TYPE_FULL_TYPOSCRIPT
        );

        if ($simulateBackend) {
            unset($GLOBALS['TYPO3_REQUEST']);
        }

        if (isset($allConfiguration['plugin.']['CunddAssetic.'])) {
            $configuration = $allConfiguration['plugin.']['CunddAssetic.'];

            return new ConfigurationProvider($configuration);
        } else {
            throw new UnexpectedValueException('Could not read configuration for "plugin.CunddAssetic"', 2047991846);
        }
    }
}
