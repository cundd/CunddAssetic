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


class LiveReload implements MessageComponentInterface {
	const MESSAGE_END = "\r\n";

	/**
	 * All connected clients
	 *
	 * @var \SplObjectStorage
	 */
	protected $clients;

	/**
	 * Handshake message
	 * @var array
	 */
	protected $handshakeMessage = array(
		'command' 		=> 'hello',
		'protocols' 	=> array(
			'http://livereload.com/protocols/official-7',
			'http://livereload.com/protocols/official-8',
			'http://livereload.com/protocols/official-9',
			'http://livereload.com/protocols/2.x-origin-version-negotiation',
			'http://livereload.com/protocols/2.x-remote-control',
		),
		'serverName' 	=> 'CunddAssetic',
	);

	/**
	 * Reload message
	 * @var array
	 */
	protected $reloadMessage = array(
		'command' 	=> 'reload',
		'path' 		=> 'path/to/file.ext',
		'liveCss' 	=> TRUE,
	);


	public function __construct() {
		$this->clients = new \SplObjectStorage;
	}

	/**
	 * Triggered when a client sends data through the socket
	 *
	 * @param  \Ratchet\ConnectionInterface $from The socket/connection that sent the message to your application
	 * @param  string                       $msg  The message received
	 * @throws \Exception
	 */
	public function onMessage(ConnectionInterface $from, $msg) {
		$numberOfReceivers = count($this->clients) - 1;

		$this->debug(
			sprintf('Connection %d sending message "%s" to %d other connection%s' . "\n",
				$from->resourceId,
				$msg,
				$numberOfReceivers,
				$numberOfReceivers == 1 ? '' : 's'
			)
		);

		$from->send(json_encode($this->handshakeMessage) . self::MESSAGE_END);


		// If the sender is the current host, pass the message to the clients
		if ($from->remoteAddress === '127.0.0.1') {
			/** @var \Ratchet\Server\IoConnection $client */
			foreach ($this->clients as $client) {
				if ($from !== $client) {
					// The sender is not the receiver, send to each client connected
					$client->send($msg);
				}
			}
		}
	}

	/**
	 * When a new connection is opened it will be passed to this method
	 * @param  ConnectionInterface $conn The socket/connection that just connected to your application
	 * @throws \Exception
	 */
	public function onOpen(ConnectionInterface $conn) {
		// Store the new connection to send messages to later
		$this->clients->attach($conn);

		$this->debug("New connection! ({$conn->resourceId})\n");
	}

	/**
	 * This is called before or after a socket is closed (depends on how it's closed).  SendMessage to $conn will not result in an error if it has already been closed.
	 * @param  ConnectionInterface $conn The socket/connection that is closing/closed
	 * @throws \Exception
	 */
	public function onClose(ConnectionInterface $conn) {
		// The connection is closed, remove it, as we can no longer send it messages
		$this->clients->detach($conn);
		$this->debug("Connection {$conn->resourceId} has disconnected\n");
	}

	/**
	 * If there is an error with one of the sockets, or somewhere in the application where an Exception is thrown,
	 * the Exception is sent back down the stack, handled by the Server and bubbled back up the application through this method
	 * @param  ConnectionInterface $conn
	 * @param  \Exception          $e
	 * @throws \Exception
	 */
	public function onError(ConnectionInterface $conn, \Exception $e) {
		$this->debug("An error has occurred: {$e->getMessage()}\n");
		$conn->close();
	}

	/**
	 * Invoked when a file changed
	 *
	 * @param string $changedFile
	 */
	public function fileDidChange($changedFile){
		$this->debug("File $changedFile did change" . PHP_EOL);

		$message = $this->reloadMessage;
		$message['path'] = $changedFile;

		/** @var \Ratchet\Server\IoConnection $client */
		foreach ($this->clients as $client) {
			$this->debug($client->remoteAddress . PHP_EOL);
			$client->send(json_encode($message) . self::MESSAGE_END);
		}
	}

	/**
	 * Prints the given message to the console
	 *
	 * @param string $message
	 */
	protected function debug($message){
		fwrite(STDOUT, $message);
	}



}