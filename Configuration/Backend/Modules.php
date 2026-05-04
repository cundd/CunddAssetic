<?php

declare(strict_types=1);

use Cundd\Assetic\Controller\AssetController;
use TYPO3\CMS\Core\Information\Typo3Version;

$version = (new Typo3Version())->getMajorVersion();

return [
    'assetic_web' => [
        'parent'            => 'web',
        'position'          => ['after' => 'web_info'],
        'access'            => 'user',
        'workspaces'        => 'live',
        'path'              => '/module/assetic/asset',
        'labels'            => 'LLL:EXT:assetic/Resources/Private/Language/locallang_cunddassetic.xlf',
        'extensionName'     => 'Assetic',
        'iconIdentifier'    => $version >= 14 ? 'ext-cundd-assetic-icon-v14' : 'ext-cundd-assetic-icon',
        'controllerActions' => [
            AssetController::class => [
                'list',
                'compile',
            ],
        ],
    ],
];
