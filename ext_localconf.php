<?php

defined('TYPO3_MODE') || die('Access denied.');

call_user_func(
    function () {
        if (!is_array($GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['assetic_cache'])) {
            $GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['assetic_cache'] = [];
        }
        $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['extbase']['commandControllers'][] = \Cundd\Assetic\Command\AsseticCommandController::class;
    }
);
