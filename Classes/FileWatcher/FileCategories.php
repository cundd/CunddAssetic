<?php

namespace Cundd\Assetic\FileWatcher;

abstract class FileCategories
{
    /**
     * List of style file suffixes
     *
     * @var array
     */
    public static $styleAssetSuffixes = ['less', 'scss', 'sass', 'css'];

    /**
     * List of script file suffixes
     *
     * @var array
     */
    public static $scriptAssetSuffixes = ['js', 'coffee'];

    /**
     * List of other file suffixes that should trigger a full page reload
     *
     * @var array
     */
    public static $otherAssetSuffixes = ['php', 'ts', 'html', 'htm'];
}
