<?php

declare(strict_types=1);

return [
    //    'access' => 'user,group',
    //    'icon'   => 'EXT:assetic/Resources/Public/Icons/logo_icon-white.svg',

    'assetic_web' => [
        'parent'            => 'web',
        'position'          => ['after' => 'web_info'],
        'access'            => 'user',
        'workspaces'        => 'live',
        'path'              => '/module/assetic/asset',
        'labels'            => 'LLL:EXT:assetic/Resources/Private/Language/locallang_cunddassetic.xlf',
        'extensionName'     => 'Assetic',
        'iconIdentifier'    => 'ext-cundd-assetic-icon',
        'controllerActions' => [
            Cundd\Assetic\Controller\AssetController::class => [
                'list',
                'compile',
            ],
        ],
    ],
];
