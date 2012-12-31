<?php
namespace Cundd\Assetic\Domain\Model;

/*
 *  Copyright notice
 *
 *  (c) 2012 Daniel Corn <info@cundd.net>, cundd
 *
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 3 of the License, or
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
 */

/**
 *
 *
 * @package assetic
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License, version 3 or later
 *
 */
class Asset extends \TYPO3\CMS\Extbase\DomainObject\AbstractEntity {

	/**
	 * Content
	 *
	 * @var \string
	 */
	protected $content;

	/**
	 * Assets last modification time (mtime)
	 *
	 * @var \integer
	 */
	protected $lastModified;

	/**
	 * Directory of the source
	 *
	 * @var \string
	 */
	protected $sourceRoot;

	/**
	 * Asset source file name
	 *
	 * @var \string
	 */
	protected $sourcePath;

	/**
	 * Asset target file name
	 *
	 * @var \string
	 */
	protected $targetPath;

	/**
	 * Returns the content
	 *
	 * @return \string $content
	 */
	public function getContent() {
		return $this->content;
	}

	/**
	 * Sets the content
	 *
	 * @param \string $content
	 * @return void
	 */
	public function setContent($content) {
		$this->content = $content;
	}

	/**
	 * Returns the lastModified
	 *
	 * @return \integer $lastModified
	 */
	public function getLastModified() {
		return $this->lastModified;
	}

	/**
	 * Sets the lastModified
	 *
	 * @param \integer $lastModified
	 * @return void
	 */
	public function setLastModified($lastModified) {
		$this->lastModified = $lastModified;
	}

	/**
	 * Returns the sourceRoot
	 *
	 * @return \string $sourceRoot
	 */
	public function getSourceRoot() {
		return $this->sourceRoot;
	}

	/**
	 * Sets the sourceRoot
	 *
	 * @param \string $sourceRoot
	 * @return void
	 */
	public function setSourceRoot($sourceRoot) {
		$this->sourceRoot = $sourceRoot;
	}

	/**
	 * Returns the sourcePath
	 *
	 * @return \string $sourcePath
	 */
	public function getSourcePath() {
		return $this->sourcePath;
	}

	/**
	 * Sets the sourcePath
	 *
	 * @param \string $sourcePath
	 * @return void
	 */
	public function setSourcePath($sourcePath) {
		$this->sourcePath = $sourcePath;
	}

	/**
	 * Returns the targetPath
	 *
	 * @return \string $targetPath
	 */
	public function getTargetPath() {
		return $this->targetPath;
	}

	/**
	 * Sets the targetPath
	 *
	 * @param \string $targetPath
	 * @return void
	 */
	public function setTargetPath($targetPath) {
		$this->targetPath = $targetPath;
	}

}
?>