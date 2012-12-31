<?php
if (!defined('TYPO3_MODE')) {
	die ('Access denied.');
}

if (TYPO3_MODE === 'BE') {
	$moduleName = $_EXTKEY;
	if (version_compare(TYPO3_version, '6.0.0', '>=')) {
		$moduleName = 'Cundd.' . $moduleName;
	}
	/**
	 * Registers a Backend Module
	 */
	//\TYPO3\CMS\Extbase\Utility\ExtensionUtility::registerModule(
	//	'Cundd.' . $_EXTKEY,
	Tx_Extbase_Utility_Extension::registerModule(
		$moduleName,
		'web',	 // Make module a submodule of 'web'
		'cunddassetic',	// Submodule key
		'',						// Position
		array(
			'Asset' => 'list, compile',
		),
		array(
			'access' => 'user,group',
			'icon'   => 'EXT:' . $_EXTKEY . '/ext_icon.png',
			'labels' => 'LLL:EXT:' . $_EXTKEY . '/Resources/Private/Language/locallang_cunddassetic.xlf',
		)
	);

}

//\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addStaticFile($_EXTKEY, 'Configuration/TypoScript', 'Cundd Assetic');
t3lib_extMgm::addStaticFile($_EXTKEY, 'Configuration/TypoScript', 'Cundd Assetic');

// \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addLLrefForTCAdescr('tx_assetic_domain_model_asset', 'EXT:assetic/Resources/Private/Language/locallang_csh_tx_assetic_domain_model_asset.xlf');
// \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::allowTableOnStandardPages('tx_assetic_domain_model_asset');
// $TCA['tx_assetic_domain_model_asset'] = array(
// 	'ctrl' => array(
// 		'title'	=> 'LLL:EXT:assetic/Resources/Private/Language/locallang_db.xlf:tx_assetic_domain_model_asset',
// 		'label' => 'content',
// 		'tstamp' => 'tstamp',
// 		'crdate' => 'crdate',
// 		'cruser_id' => 'cruser_id',
// 		'dividers2tabs' => TRUE,
// 		'sortby' => 'sorting',
// 		'versioningWS' => 2,
// 		'versioning_followPages' => TRUE,
// 		'origUid' => 't3_origuid',
// 		'languageField' => 'sys_language_uid',
// 		'transOrigPointerField' => 'l10n_parent',
// 		'transOrigDiffSourceField' => 'l10n_diffsource',
// 		'delete' => 'deleted',
// 		'enablecolumns' => array(
// 			'disabled' => 'hidden',
// 			'starttime' => 'starttime',
// 			'endtime' => 'endtime',
// 		),
// 		'searchFields' => 'content,last_modified,source_root,source_path,target_path,',
// 		'dynamicConfigFile' => \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath($_EXTKEY) . 'Configuration/TCA/Asset.php',
// 		'iconfile' => \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extRelPath($_EXTKEY) . 'Resources/Public/Icons/tx_assetic_domain_model_asset.gif'
// 	),
// );

?>