<?php

declare(strict_types=1);

namespace Cundd\Assetic\Configuration;

class LiveReloadConfiguration
{
    /**
     * @param bool $isEnabled      Specify if LiveReload support is generally enabled
     * @param int  $port           Port to use for LiveReload server
     * @param bool $skipServerTest Return if the LiveReload JavaScript code
     *                             should be inserted even if the server
     *                             connection is not available (ignored if `isEnabled` is `FALSE`)
     */
    public function __construct(
        public readonly bool $isEnabled,
        public readonly int $port,
        public readonly bool $skipServerTest,
    ) {
    }
}
