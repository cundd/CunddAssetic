<?php
if (!defined('TYPO3_MODE')) {
	die ('Access denied.');
}

if (!is_array($TYPO3_CONF_VARS['SYS']['caching']['cacheConfigurations']['assetic_cache'])) {
    $TYPO3_CONF_VARS['SYS']['caching']['cacheConfigurations']['assetic_cache'] = array();
}
?>