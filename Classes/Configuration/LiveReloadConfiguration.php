<?php
declare(strict_types=1);

namespace Cundd\Assetic\Configuration;

class LiveReloadConfiguration
{
    /**
     * @var int
     */
    private $port;

    /**
     * @var bool
     */
    private $addJavascript;

    /**
     * @var bool
     */
    private $skipServerTest;

    /**
     * LiveReload Configuration constructor
     *
     * @param int  $port
     * @param bool $addJavascript
     * @param bool $skipServerTest
     */
    public function __construct(int $port, bool $addJavascript, bool $skipServerTest)
    {
        $this->port = $port;
        $this->addJavascript = $addJavascript;
        $this->skipServerTest = $skipServerTest;
    }

    /**
     * Return if the LiveReload support is generally enabled
     *
     * @return bool
     */
    public function isEnabled(): bool
    {
        return $this->getAddJavascript();
    }

    /**
     * Return the LiveReload server port
     *
     * @return int
     */
    public function getPort(): int
    {
        return $this->port;
    }

    /**
     * Return if the LiveReload JavaScript code should be added to the output
     *
     * @return bool
     */
    public function getAddJavascript(): bool
    {
        return $this->addJavascript;
    }

    /**
     * Return if the LiveReload JavaScript code should be inserted even if the server connection is not available
     *
     * This is ignored if `addJavascript` is `FALSE`
     *
     * @return bool
     */
    public function getSkipServerTest(): bool
    {
        return $this->skipServerTest;
    }
}
