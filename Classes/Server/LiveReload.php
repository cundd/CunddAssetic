<?php
/*
 *  Copyright notice
 *
 *  (c) 2013 Andreas Thurnheer-Meier <tma@iresults.li>, iresults
 *  Daniel Corn <cod@iresults.li>, iresults
 *
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 */

/**
 * @author COD
 * Created 18.10.13 09:08
 */


namespace Cundd\Assetic\Server;

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use Ratchet\Mock\Connection;
use Ratchet\Wamp\WampConnection;

/**
 * LiveReload message component
 *
 * @package Cundd\Assetic\Server
 */
class LiveReload implements MessageComponentInterface
{
    /**
     * Emergency: system is unusable
     *
     * You'd likely not be able to reach the system. You better have an SLA in
     * place when this happens.
     *
     * @var integer
     */
    const LOG_LEVEL_EMERGENCY = 0;

    /**
     * Alert: action must be taken immediately
     *
     * Example: Entire website down, database unavailable, etc.
     *
     * @var integer
     */
    const LOG_LEVEL_ALERT = 1;

    /**
     * Critical: critical conditions
     *
     * Example: unexpected exception.
     *
     * @var integer
     */
    const LOG_LEVEL_CRITICAL = 2;

    /**
     * Error: error conditions
     *
     * Example: Runtime error.
     *
     * @var integer
     */
    const LOG_LEVEL_ERROR = 3;

    /**
     * Warning: warning conditions
     *
     * Examples: Use of deprecated APIs, undesirable things that are not
     * necessarily wrong.
     *
     * @var integer
     */
    const LOG_LEVEL_WARNING = 4;

    /**
     * Notice: normal but significant condition
     *
     * Example: things you should have a look at, nothing to worry about though.
     *
     * @var integer
     */
    const LOG_LEVEL_NOTICE = 5;

    /**
     * Informational: informational messages
     *
     * Examples: User logs in, SQL logs.
     *
     * @var integer
     */
    const LOG_LEVEL_INFO = 6;

    /**
     * Debug: debug-level messages
     *
     * Example: Detailed status information.
     *
     * @var integer
     */
    const LOG_LEVEL_DEBUG = 7;

    /**
     * Reverse look up of log level to level name.
     *
     * @var array
     */
    protected static $logLevelPrefix = array(
        self::LOG_LEVEL_EMERGENCY => '!!!',
        self::LOG_LEVEL_ALERT     => '!!',
        self::LOG_LEVEL_CRITICAL  => '!',
        self::LOG_LEVEL_ERROR     => '!',
        self::LOG_LEVEL_WARNING   => '(w)',
        self::LOG_LEVEL_NOTICE    => '(n)',
        self::LOG_LEVEL_INFO      => '(i)',
        self::LOG_LEVEL_DEBUG     => '(d)',
    );

    /**
     * End of a message
     */
    const MESSAGE_END = "\r\n";

    /**
     * All connected clients
     *
     * @var \SplObjectStorage
     */
    protected $clients;

    /**
     * Handshake message
     *
     * @var array
     */
    protected $handshakeMessage = array(
        'command'    => 'hello',
        'protocols'  => array(
            'http://livereload.com/protocols/official-7',
            'http://livereload.com/protocols/official-8',
            //'http://livereload.com/protocols/official-9',
            //'http://livereload.com/protocols/2.x-origin-version-negotiation',
            //'http://livereload.com/protocols/2.x-remote-control',
        ),
        'serverName' => 'CunddAssetic',
    );

    /**
     * Reload message
     *
     * @var array
     */
    protected $reloadMessage = array(
        'command' => 'reload',
        'path'    => 'path/to/file.ext',
        'liveCss' => true,
    );

    /**
     * Alert message
     *
     * @var array
     */
    protected $alertMessage = array(
        'command' => 'alert',
        'message' => 'Hy',
    );


    public function __construct()
    {
        $this->clients = new \SplObjectStorage;
    }

    /**
     * Triggered when a client sends data through the socket
     *
     * @param  \Ratchet\ConnectionInterface $from The socket/connection that sent the message to your application
     * @param  string                       $msg  The message received
     * @throws \Exception
     */
    public function onMessage(ConnectionInterface $from, $msg)
    {
        /** @var WampConnection|Connection $from */
        $this->debug(
            sprintf(
                'Received message "%s" from connection %d address %s' . PHP_EOL,
                $msg,
                $from->resourceId,
                $from->remoteAddress
            ),
            self::LOG_LEVEL_DEBUG
        );

        // If the sender is the current host, pass the message to the clients
        if ($from->remoteAddress === '127.0.0.1') {
            /** @var \Ratchet\Server\IoConnection $client */
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
     * @param  ConnectionInterface $conn The socket/connection that just connected to your application
     * @throws \Exception
     */
    public function onOpen(ConnectionInterface $conn)
    {
        /** @var WampConnection|Connection $conn */
        // Store the new connection to send messages to later
        $this->clients->attach($conn);
        $this->send($conn, $this->handshakeMessage);

        $this->debug("New connection ({$conn->resourceId})\n", '+');
    }

    /**
     * @param ConnectionInterface $connection
     * @param mixed               $message
     */
    protected function send($connection, $message)
    {
        if (is_string($message)) {
            if (substr($message, -strlen(self::MESSAGE_END)) !== self::MESSAGE_END) {
                $message .= self::MESSAGE_END;
            }
        } else {
            if (defined('JSON_UNESCAPED_SLASHES')) {
                $message = json_encode($message, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
            } else {
                $message = str_replace('\\/', '/', json_encode($message));
            }
            $message .= self::MESSAGE_END;
        }

        $connection->send($message);
    }

    /**
     * This is called before or after a socket is closed (depends on how it's closed).  SendMessage to $conn will not result in an error if it has already been closed.
     *
     * @param  ConnectionInterface $conn The socket/connection that is closing/closed
     * @throws \Exception
     */
    public function onClose(ConnectionInterface $conn)
    {
        /** @var WampConnection|Connection $conn */
        // The connection is closed, remove it, as we can no longer send it messages
        $this->clients->detach($conn);
        $this->debug("Connection {$conn->resourceId} has disconnected\n", '-');
    }

    /**
     * If there is an error with one of the sockets, or somewhere in the application where an Exception is thrown,
     * the Exception is sent back down the stack, handled by the Server and bubbled back up the application through this method
     *
     * @param  ConnectionInterface $conn
     * @param  \Exception          $e
     * @throws \Exception
     */
    public function onError(ConnectionInterface $conn, \Exception $e)
    {
        $this->debug("An error has occurred: {$e->getMessage()}\n", self::LOG_LEVEL_ERROR);
        $conn->close();
    }

    /**
     * Invoked when a file changed
     *
     * @param string $changedFile
     * @param bool   $liveCss
     */
    public function fileDidChange($changedFile, $liveCss = true)
    {
        $this->debug("File '$changedFile' did change" . PHP_EOL, self::LOG_LEVEL_INFO);

        $message = $this->reloadMessage;
        $message['path'] = $changedFile;

        if (!$liveCss) {
            unset($message['liveCss']);
        }

        $this->debug('Notify ' . count($this->clients) . ' clients' . PHP_EOL, self::LOG_LEVEL_DEBUG);
        $this->debug('Sending ' . (json_encode($message)) . ' ' . PHP_EOL, self::LOG_LEVEL_DEBUG);

        /** @var \Ratchet\Server\IoConnection $client */
        foreach ($this->clients as $client) {
            $this->send($client, $message);
        }
    }

    /**
     * Prints the given message to the console
     *
     * @param string $message
     * @param int    $logLevel
     */
    protected function debug($message, $logLevel = null)
    {
        if ($logLevel !== null) {
            $messagePrefix = null;
            if (is_int($logLevel)) {
                $messagePrefix = isset(self::$logLevelPrefix[$logLevel]) ? self::$logLevelPrefix[$logLevel] : '';
            } elseif (is_string($logLevel)) {
                $messagePrefix = "($logLevel)";
            }
            if ($messagePrefix) {
                $message = $messagePrefix . ' ' . $message;
            }
        }
        fwrite(STDOUT, $message);
    }
}
