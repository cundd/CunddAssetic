<?php

use TYPO3\CMS\Core\Imaging\IconProvider\SvgIconProvider;

return [
    'ext-cundd-assetic-icon' => [
        'provider' => SvgIconProvider::class,
        'source'   => 'EXT:assetic/Resources/Public/Icons/logo_icon-white.svg',
    ],
    'ext-cundd-assetic-icon-v14' => [
        'provider' => SvgIconProvider::class,
        'source'   => 'EXT:assetic/Resources/Public/Icons/logo_icon-white-v14.svg',
    ],
];
