<?php

declare(strict_types=1);

namespace Cundd\Assetic\Utility;

use function class_exists;

class Autoloader
{
    public static function register(): void
    {
        if (class_exists(\Cundd\CunddComposer\Autoloader::class)) {
            \Cundd\CunddComposer\Autoloader::register();
        }
    }
}
