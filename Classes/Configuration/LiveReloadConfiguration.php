<?php

declare(strict_types=1);

namespace Cundd\Assetic\Configuration;

class LiveReloadConfiguration
{
    private int $port;

    private bool $addJavascript;

    private bool $skipServerTest;

    public function __construct(int $port, bool $addJavascript, bool $skipServerTest)
    {
        $this->port = $port;
        $this->addJavascript = $addJavascript;
        $this->skipServerTest = $skipServerTest;
    }

    /**
     * Return if the LiveReload support is generally enabled
     */
    public function isEnabled(): bool
    {
        return $this->getAddJavascript();
    }

    /**
     * Return the LiveReload server port
     */
    public function getPort(): int
    {
        return $this->port;
    }

    /**
     * Return if the LiveReload JavaScript code should be added to the output
     */
    public function getAddJavascript(): bool
    {
        return $this->addJavascript;
    }

    /**
     * Return if the LiveReload JavaScript code should be inserted even if the server connection is not available
     *
     * This is ignored if `addJavascript` is `FALSE`
     */
    public function getSkipServerTest(): bool
    {
        return $this->skipServerTest;
    }
}
