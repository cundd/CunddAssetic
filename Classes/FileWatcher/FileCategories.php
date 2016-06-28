<?php
/**
 * Created by PhpStorm.
 * User: daniel
 * Date: 24/02/16
 * Time: 21:55
 */

namespace Cundd\Assetic\FileWatcher;

abstract class FileCategories
{
    /**
     * List of style file suffixes
     *
     * @var array
     */
    public static $styleAssetSuffixes = array('less', 'scss', 'sass', 'css');

    /**
     * List of script file suffixes
     *
     * @var array
     */
    public static $scriptAssetSuffixes = array('js', 'coffee');

    /**
     * List of other file suffixes that should trigger a full page reload
     *
     * @var array
     */
    public static $otherAssetSuffixes = array('php', 'ts', 'html', 'htm');
}
