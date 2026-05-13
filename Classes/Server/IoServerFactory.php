<?php

declare(strict_types=1);

namespace Cundd\Assetic\Server;

use Cundd\Assetic\Configuration;
use Cundd\Assetic\ValueObject\CompilationContext;
use Ratchet\Http\HttpServer;
use Ratchet\Server\IoServer as RatchetIoServer;
use Ratchet\WebSocket\WsServer;
use React\EventLoop\Loop;
use React\Socket\SecureServer;
use React\Socket\SocketServer;

/**
 * @phpstan-type SecureServerContext array{
 *     local_cert: string,
 *     local_pk?: string,
 *     allow_self_signed: true,
 *     verify_peer: false
 * }
 */
final class IoServerFactory
{
    /**
     * @param callable(): void     $periodicCallback
     * @param non-empty-string     $address
     * @param ?SecureServerContext $secureServerContext
     */
    public function buildServer(
        Configuration $configuration,
        CompilationContext $compilationContext,
        LiveReloadComponent $liveReloadServer,
        string $address,
        int $port,
        float $notificationDelay,
        ?array $secureServerContext,
        float $periodicInterval,
        callable $periodicCallback,
    ): IoServer {
        $component = new HttpServer(new WsServer($liveReloadServer));

        if (!$secureServerContext) {
            $server = RatchetIoServer::factory(
                $component,
                $port,
                $address
            );
        } else {
            $loop = Loop::get();

            $server = new SecureServer(
                new SocketServer($address . ':' . $port),
                $loop,
                $secureServerContext
            );

            $server = new RatchetIoServer($component, $server, $loop);
        }

        assert(null !== $server->loop);

        $server->loop->addPeriodicTimer(
            $periodicInterval,
            $periodicCallback,
        );
        $liveReloadServer->setEventLoop($server->loop);

        return new IoServer($server);
    }
}
