<?php

/***************************************************************
 * Extension Manager/Repository config file for ext "assetic".
 *
 * Auto generated 14-09-2013 14:40
 *
 * Manual updates:
 * Only the data in the array - everything else is removed by next
 * writing. "version" and "dependencies" must not be touched!
 ***************************************************************/

$EM_CONF[$_EXTKEY] = [
    'title'            => 'Cundd Assetic',
    'description'      => 'Assetic for TYPO3 CMS (https://github.com/cundd/CunddAssetic)',
    'category'         => 'services',
    'author'           => 'Daniel Corn',
    'author_email'     => 'info@cundd.net',
    'author_company'   => 'cundd',
    'state'            => 'stable',
    'clearCacheOnLoad' => 0,
    'version'          => '4.2.0',
    'constraints'      => [
        'depends' => [
            'typo3' => '12.4-12.4.99',
        ],
        'suggests' => [
            'cundd_composer' => '3.0-5.99',
        ],
    ],
];
