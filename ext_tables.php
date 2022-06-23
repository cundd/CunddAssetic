<?php
if (!defined('TYPO3_MODE')) {
    die('Access denied.');
}
call_user_func(
    static function () {
        \TYPO3\CMS\Extbase\Utility\ExtensionUtility::registerModule(
            'Assetic',
            'web',
            'cunddassetic',
            '',
            [
                \Cundd\Assetic\Controller\AssetController::class => 'list, compile',
            ],
            [
                'access' => 'user,group',
                'icon'   => 'EXT:assetic/Resources/Public/Icons/logo_icon-white.svg',
                'labels' => 'LLL:EXT:assetic/Resources/Private/Language/locallang_cunddassetic.xlf',
            ]
        );
    }
);
