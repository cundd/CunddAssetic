<?php

declare(strict_types=1);

namespace Cundd\Assetic\Utility;

use function intval;

class BackendUserUtility
{
    /**
     * Return if a backend user is logged in
     *
     * @return bool
     */
    public static function isUserLoggedIn(): bool
    {
        return isset($GLOBALS['BE_USER']->user) && 0 < intval($GLOBALS['BE_USER']->user['uid']);
    }
}
