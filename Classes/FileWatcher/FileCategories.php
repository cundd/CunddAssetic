<?php

declare(strict_types=1);

namespace Cundd\Assetic\FileWatcher;

abstract class FileCategories
{
    /**
     * List of style file suffixes
     */
    public static array $styleAssetSuffixes = ['less', 'scss', 'sass', 'css'];

    /**
     * List of script file suffixes
     */
    public static array $scriptAssetSuffixes = ['js', 'coffee'];

    /**
     * List of other file suffixes that should trigger a full page reload
     */
    public static array $otherAssetSuffixes = ['php', 'ts', 'typoscript', 'html', 'htm'];
}
