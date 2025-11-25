<?php

declare(strict_types=1);

namespace Cundd\Assetic\Utility;

use function intval;

class BackendUserUtility
{
    /**
     * Return if a backend user is logged in
     */
    public static function isUserLoggedIn(): bool
    {
        return is_object($GLOBALS['BE_USER'] ?? null)
            && isset($GLOBALS['BE_USER']->user)
            && 0 < intval($GLOBALS['BE_USER']->user['uid']);
    }
}
