<?php

declare(strict_types=1);

namespace Cundd\Assetic\Utility;

/**
 * Helper class to read configuration
 */
class ConfigurationUtility
{
    /**
     * Return the relevant domain to be attached to the cache identifier to distinguish the websites in a multi-domain
     * installation
     */
    public static function getDomainIdentifier(): string
    {
        return '';
    }
}
