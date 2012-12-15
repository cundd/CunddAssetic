<?php
if (!defined('TYPO3_MODE')) {
	die('Access denied.');
}

// t3lib_extMgm::addPItoST43($_EXTKEY, 'Classes/Cundd/Assetic/Plugin.php', '_plugin', 'none', 1);

// if (TYPO3_MODE === 'BE') {
// 	$extConf = unserialize($TYPO3_CONF_VARS['EXT']['extConf']['sassify']);
// 	if (!isset($extConf['enableClearAllCacheHook']) || (boolean)$extConf['enableClearAllCacheHook']) {
// 			// Remove Sass files if all caches are cleared
// 		$TYPO3_CONF_VARS['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['clearCachePostProc']['sassify'] =
// 			'EXT:sassify/Classes/CacheHook.php:&tx_Sassify_CacheHook->clearCachePostProc';
// 	} else {
// 		$TYPO3_CONF_VARS['SC_OPTIONS']['additionalBackendItems']['cacheActions'][] = 'EXT:sassify/Classes/CacheMenu.php:&tx_Sassify_CacheMenu';
// 		$TYPO3_CONF_VARS['BE']['AJAX']['tx_sassify::clearCache'] = 'EXT:sassify/Classes/CacheHook.php:tx_Sassify_CacheHook->ajaxClearCache';
// 	}
// }

?>