<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2011 networkteam GmbH <typo3@networkteam.com>
*  All rights reserved
*
*  This script is part of the TYPO3 project. The TYPO3 project is
*  free software; you can redistribute it and/or modify
*  it under the terms of the GNU General Public License as published by
*  the Free Software Foundation; either version 2 of the License, or
*  (at your option) any later version.
*
*  The GNU General Public License can be found at
*  http://www.gnu.org/copyleft/gpl.html.
*
*  This script is distributed in the hope that it will be useful,
*  but WITHOUT ANY WARRANTY; without even the implied warranty of
*  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
*  GNU General Public License for more details.
*
*  This copyright notice MUST APPEAR in all copies of the script!
***************************************************************/

/**
 * Clear cache hook to clear Sass cache on clear all cache.
 *
 * @package sassify
 */
class Tx_Sassify_CacheHook {

	/**
	 * Clear the internal Sass cache on "Clear all caches"
	 *
	 * Will be called by clearCachePostProc hook.
	 *
	 * @param array $parameters
	 * @param t3lib_TCEmain $tcemain
	 * @return void
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 */
    public function clearCachePostProc($parameters, $tcemain) {
		if ($parameters['cacheCmd'] === 'all') {
			$this->clearCache();
		}
	}

	/**
	 * Clear the internal Sass cache on cache menu request
	 *
	 * @return void
	 * @author Christopher Hlubek <hlubek@networkteam.com>
	 */
	public function ajaxClearCache() {
		$tceMain = t3lib_div::makeInstance('t3lib_TCEmain');
		$tceMain->start(array(), array());
		$tceMain->clear_cacheCmd('pages');

		$this->clearCache();
	}

	/**
	 * @return void
	 */
	protected function clearCache() {
		array_map('unlink', glob(PATH_site . 'typo3temp/sass_cache/*.sassc'));
		array_map('unlink', glob(PATH_site . 'typo3temp/sass_css/*.css'));
	}

}
?>