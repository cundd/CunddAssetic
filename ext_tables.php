<?php
if (!defined('TYPO3_MODE')) {
    die('Access denied.');
}
call_user_func(
    function () {
        if (TYPO3_MODE === 'BE') {
            /*
             * Registers a Backend Module
             */
            \TYPO3\CMS\Extbase\Utility\ExtensionUtility::registerModule(
                'Cundd.Assetic',
                'web', // Make module a submodule of 'web'
                'cunddassetic', // Submodule key
                '', // Position
                [
                    'Asset' => 'list, compile',
                ],
                [
                    'access' => 'user,group',
                    'icon'   => 'EXT:assetic/Resources/Public/Icons/logo_icon-white.svg',
                    'labels' => 'LLL:EXT:assetic/Resources/Private/Language/locallang_cunddassetic.xlf',
                ]
            );
        }

        TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addStaticFile(
            'assetic',
            'Configuration/TypoScript',
            'Cundd Assetic'
        );
    }
);
