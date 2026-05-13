<?php

declare(strict_types=1);

namespace Cundd\Assetic\Server;

use Ratchet\Server\IoServer as RatchetIoServer;

/**
 * Abstraction wrapper around Ratchet's IoServer
 */
final readonly class IoServer
{
    public function __construct(private RatchetIoServer $ioServer)
    {
    }

    /**
     * Run the application by entering the event loop
     */
    public function run(): void
    {
        $this->ioServer->run();
    }
}
