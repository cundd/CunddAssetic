<?php

declare(strict_types=1);

namespace Cundd\Assetic\Configuration;

use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManager;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;
use UnexpectedValueException;

class ConfigurationProviderFactory
{
    public function build(): ConfigurationProviderInterface
    {
        $configurationManager = GeneralUtility::makeInstance(ConfigurationManager::class);

        $allConfiguration = $configurationManager->getConfiguration(
            ConfigurationManagerInterface::CONFIGURATION_TYPE_FULL_TYPOSCRIPT
        );
        if (isset($allConfiguration['plugin.']['CunddAssetic.'])) {
            $configuration = $allConfiguration['plugin.']['CunddAssetic.'];

            return new ConfigurationProvider($configuration);
        } else {
            throw new UnexpectedValueException('Could not read configuration for "plugin.CunddAssetic"');
        }
    }
}
