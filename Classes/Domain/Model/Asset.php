<?php

namespace Cundd\Assetic\Domain\Model;

use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;

class Asset extends AbstractEntity
{

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
    public function getContent()
    {
        return $this->content;
    }

    /**
     * Sets the content
     *
     * @param \string $content
     * @return void
     */
    public function setContent($content)
    {
        $this->content = $content;
    }

    /**
     * Returns the lastModified
     *
     * @return \integer $lastModified
     */
    public function getLastModified()
    {
        return $this->lastModified;
    }

    /**
     * Sets the lastModified
     *
     * @param \integer $lastModified
     * @return void
     */
    public function setLastModified($lastModified)
    {
        $this->lastModified = $lastModified;
    }

    /**
     * Returns the sourceRoot
     *
     * @return \string $sourceRoot
     */
    public function getSourceRoot()
    {
        return $this->sourceRoot;
    }

    /**
     * Sets the sourceRoot
     *
     * @param \string $sourceRoot
     * @return void
     */
    public function setSourceRoot($sourceRoot)
    {
        $this->sourceRoot = $sourceRoot;
    }

    /**
     * Returns the sourcePath
     *
     * @return \string $sourcePath
     */
    public function getSourcePath()
    {
        return $this->sourcePath;
    }

    /**
     * Sets the sourcePath
     *
     * @param \string $sourcePath
     * @return void
     */
    public function setSourcePath($sourcePath)
    {
        $this->sourcePath = $sourcePath;
    }

    /**
     * Returns the targetPath
     *
     * @return \string $targetPath
     */
    public function getTargetPath()
    {
        return $this->targetPath;
    }

    /**
     * Sets the targetPath
     *
     * @param \string $targetPath
     * @return void
     */
    public function setTargetPath($targetPath)
    {
        $this->targetPath = $targetPath;
    }
}
