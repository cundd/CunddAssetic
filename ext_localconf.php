<?php

defined('TYPO3') or die();

call_user_func(
    static function () {
        if (!isset($GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['assetic_cache'])
            || !is_array($GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['assetic_cache'])) {
            $GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['assetic_cache'] = [];
        }
    }
);
