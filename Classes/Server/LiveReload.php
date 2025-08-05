<?php

declare(strict_types=1);

namespace Cundd\Assetic\Server;

use Exception;
use Ratchet\ConnectionInterface;
use Ratchet\MessageComponentInterface;
use Ratchet\Mock\Connection;
use Ratchet\Server\IoConnection;
use Ratchet\Wamp\WampConnection;
use React\EventLoop\LoopInterface;
use SplObjectStorage;

use function date;
use function json_encode;

use const JSON_PRETTY_PRINT;
use const JSON_UNESCAPED_SLASHES;

/**
 * LiveReload message component
 */
class LiveReload implements MessageComponentInterface
{
    /**
     * Emergency: system is unusable
     *
     * You'd likely not be able to reach the system. You better have an SLA in
     * place when this happens.
     *
     * @var int
     */
    private const LOG_LEVEL_EMERGENCY = 0;

    /**
     * Alert: action must be taken immediately
     *
     * Example: Entire website down, database unavailable, etc.
     *
     * @var int
     */
    private const LOG_LEVEL_ALERT = 1;

    /**
     * Critical: critical conditions
     *
     * Example: unexpected exception.
     *
     * @var int
     */
    private const LOG_LEVEL_CRITICAL = 2;

    /**
     * Error: error conditions
     *
     * Example: Runtime error.
     *
     * @var int
     */
    private const LOG_LEVEL_ERROR = 3;

    /**
     * Warning: warning conditions
     *
     * Examples: Use of deprecated APIs, undesirable things that are not
     * necessarily wrong.
     *
     * @var int
     */
    private const LOG_LEVEL_WARNING = 4;

    /**
     * Notice: normal but significant condition
     *
     * Example: things you should have a look at, nothing to worry about though.
     *
     * @var int
     */
    private const LOG_LEVEL_NOTICE = 5;

    /**
     * Informational: informational messages
     *
     * Examples: User logs in, SQL logs.
     *
     * @var int
     */
    private const LOG_LEVEL_INFO = 6;

    /**
     * Debug: debug-level messages
     *
     * Example: Detailed status information.
     *
     * @var int
     */
    private const LOG_LEVEL_DEBUG = 7;

    /**
     * Reverse look up of log level to level name.
     *
     * @var array<int,string>
     */
    protected static array $logLevelPrefix = [
        self::LOG_LEVEL_EMERGENCY => '!!!',
        self::LOG_LEVEL_ALERT     => '!!',
        self::LOG_LEVEL_CRITICAL  => '!',
        self::LOG_LEVEL_ERROR     => '!',
        self::LOG_LEVEL_WARNING   => '(w)',
        self::LOG_LEVEL_NOTICE    => '(n)',
        self::LOG_LEVEL_INFO      => '(i)',
        self::LOG_LEVEL_DEBUG     => '(d)',
    ];

    /**
     * End of a message
     */
    private const MESSAGE_END = "\r\n";

    /**
     * All connected clients
     *
     * @var SplObjectStorage<ConnectionInterface,mixed>
     */
    protected SplObjectStorage $clients;

    /**
     * Handshake message
     *
     * @var array<string,mixed>
     */
    protected array $handshakeMessage = [
        'command'   => 'hello',
        'protocols' => [
            'http://livereload.com/protocols/official-7',
            'http://livereload.com/protocols/official-8',
            // 'http://livereload.com/protocols/official-9',
            // 'http://livereload.com/protocols/2.x-origin-version-negotiation',
            // 'http://livereload.com/protocols/2.x-remote-control',
        ],
        'serverName' => 'CunddAssetic',
    ];

    /**
     * Reload message
     *
     * @var array<string,mixed>
     */
    protected array $reloadMessage = [
        'command' => 'reload',
        'path'    => 'path/to/file.ext',
        'liveCss' => true,
    ];

    /**
     * Alert message
     *
     * @var array<string,mixed>
     */
    protected array $alertMessage = [
        'command' => 'alert',
        'message' => 'Hy',
    ];

    private LoopInterface $eventLoop;

    /**
     * @param int|float $notificationDelay Number of seconds to wait before sending the reload command to the clients
     */
    public function __construct(private readonly int|float $notificationDelay)
    {
        $this->clients = new SplObjectStorage();
    }

    /**
     * Attach the event loop to the server to allow sending delayed responses
     */
    public function setEventLoop(LoopInterface $loop): void
    {
        $this->eventLoop = $loop;
    }

    /**
     * Triggered when a client sends data through the socket
     *
     * @param ConnectionInterface $from The socket/connection that sent the message to your application
     * @param string              $msg  The message received
     *
     * @throws Exception
     */
    public function onMessage(ConnectionInterface $from, $msg): void
    {
        /* @var WampConnection|Connection $from */
        $this->debugLine(
            sprintf(
                'Received message "%s" from connection %d address %s',
                $msg,
                $this->getResourceId($from),
                $this->getRemoteAddress($from),
            ),
            self::LOG_LEVEL_DEBUG
        );

        // If the sender is the current host, pass the message to the clients
        if ('127.0.0.1' === $this->getRemoteAddress($from)) {
            /** @var IoConnection $client */
            foreach ($this->clients as $client) {
                if ($from !== $client) {
                    // The sender is not the receiver, send to each client connected
                    $this->send($client, $this->alertMessage);
                }
            }
        }
    }

    /**
     * When a new connection is opened it will be passed to this method
     *
     * @param ConnectionInterface $conn The socket/connection that just connected to your application
     *
     * @throws Exception
     */
    public function onOpen(ConnectionInterface $conn): void
    {
        /* @var WampConnection|Connection $conn */
        // Store the new connection to send messages to later
        $this->clients->attach($conn);
        $this->send($conn, $this->handshakeMessage);

        $this->debugLine("New connection ({$this->getResourceId($conn)})", '+');
    }

    /**
     * This is called before or after a socket is closed (depends on how it's closed).  SendMessage to $conn will not result in an error if it has already been closed.
     *
     * @param ConnectionInterface $conn The socket/connection that is closing/closed
     *
     * @throws Exception
     */
    public function onClose(ConnectionInterface $conn): void
    {
        /* @var WampConnection|Connection $conn */
        // The connection is closed, remove it, as we can no longer send it messages
        $this->clients->detach($conn);
        $this->debugLine("Connection {$this->getResourceId($conn)} has disconnected", '-');
    }

    /**
     * If there is an error with one of the sockets, or somewhere in the application where an Exception is thrown,
     * the Exception is sent back down the stack, handled by the Server and bubbled back up the application through this method
     *
     * @throws Exception
     */
    public function onError(ConnectionInterface $conn, Exception $e): void
    {
        $this->debugLine("An error has occurred: {$e->getMessage()}", self::LOG_LEVEL_ERROR);
        $conn->close();
    }

    /**
     * Invoked when a file changed
     */
    public function fileDidChange(string $changedFile, bool $liveCss): void
    {
        $this->debug("File '$changedFile' did change" . PHP_EOL, self::LOG_LEVEL_INFO);

        $message = $this->reloadMessage;
        $message['path'] = $changedFile;

        if (!$liveCss) {
            unset($message['liveCss']);
        }

        $this->debugLine(
            sprintf(
                'Notify %d clients%s',
                count($this->clients),
                ($this->notificationDelay > 0 ? " (with {$this->notificationDelay} seconds notification delay)" : '')
            ),
            self::LOG_LEVEL_DEBUG
        );
        $this->debugLine('Sending ' . (json_encode($message, JSON_UNESCAPED_SLASHES)) . ' ', self::LOG_LEVEL_DEBUG);

        $this->eventLoop->addTimer(
            $this->notificationDelay,
            function () use ($message) {
                foreach ($this->clients as $client) {
                    $this->send($client, $message);
                }
            }
        );
    }

    protected function send(ConnectionInterface $connection, mixed $message): void
    {
        if (is_string($message)) {
            if (self::MESSAGE_END !== substr($message, -strlen(self::MESSAGE_END))) {
                $message .= self::MESSAGE_END;
            }
        } else {
            $message = json_encode($message, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . self::MESSAGE_END;
        }

        $connection->send($message);
    }

    /**
     * Print the given message to the console
     *
     * @param string|int|null $logLevel Log Level or symbol
     */
    protected function debug(string $message, $logLevel = null): void
    {
        if (null !== $logLevel) {
            $messagePrefix = date('r') . ' ';
            if (is_int($logLevel)) {
                $messagePrefix .= self::$logLevelPrefix[$logLevel] ?? '';
            } elseif (is_string($logLevel)) {
                $messagePrefix .= "($logLevel)";
            }
            $message = $messagePrefix . ' ' . $message;
        }
        fwrite(STDOUT, $message);
    }

    /**
     * Print the given message to the console
     *
     * @param string|int|null $logLevel Log Level or symbol
     */
    protected function debugLine(string $message, $logLevel = null): void
    {
        $this->debug($message . PHP_EOL, $logLevel);
    }

    private function getResourceId(ConnectionInterface $connection): string
    {
        // @phpstan-ignore property.notFound
        return (string) $connection->resourceId;
    }

    private function getRemoteAddress(ConnectionInterface $connection): string
    {
        // @phpstan-ignore property.notFound
        return (string) $connection->remoteAddress;
    }
}
