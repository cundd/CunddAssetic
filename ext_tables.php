<?php
if (!defined('TYPO3_MODE')) {
    die ('Access denied.');
}

if (TYPO3_MODE === 'BE') {
    /*
     * Registers a Backend Module
     */
    \TYPO3\CMS\Extbase\Utility\ExtensionUtility::registerModule(
        'Cundd.' . $_EXTKEY,
        'web', // Make module a submodule of 'web'
        'cunddassetic', // Submodule key
        '', // Position
        array(
            'Asset' => 'list, compile',
        ),
        array(
            'access' => 'user,group',
            'icon'   => 'EXT:' . $_EXTKEY . '/ext_icon.png',
            'labels' => 'LLL:EXT:' . $_EXTKEY . '/Resources/Private/Language/locallang_cunddassetic.xlf',
        )
    );

}

TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addStaticFile($_EXTKEY, 'Configuration/TypoScript', 'Cundd Assetic');
?>