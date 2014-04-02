<?php
if (!defined('TYPO3_MODE')) {
	die ('Access denied.');
}

if (!is_array($TYPO3_CONF_VARS['SYS']['caching']['cacheConfigurations']['assetic_cache'])) {
    $TYPO3_CONF_VARS['SYS']['caching']['cacheConfigurations']['assetic_cache'] = array();
}


if (TYPO3_MODE === 'BE' && version_compare(TYPO3_version, '6.0') >= 0) {
	$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['extbase']['commandControllers'][] = 'Cundd\\Assetic\\Command\\CompileCommandController';
}
?>