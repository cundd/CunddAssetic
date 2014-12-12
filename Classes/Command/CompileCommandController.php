<?php
/*
 *  Copyright notice
 *
 *  (c) 2012 Daniel Corn <info@cundd.net>, cundd
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
 * Created 11.10.13 15:16
 */

namespace Cundd\Assetic\Command;

use Cundd\Assetic\Server\LiveReload;
use Cundd\Assetic\Utility\ConfigurationUtility;
use Ratchet\Server\IoServer;
use Ratchet\WebSocket\WsServer;
use React\EventLoop\Factory as LoopFactory;
use React\Socket\Server as ReactServer;

use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;
use TYPO3\CMS\Extbase\Mvc\Controller\CommandController;

\Tx_CunddComposer_Autoloader::register();

class CompileCommandController extends CommandController {
	/* MWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWM */
	/* ESCAPE CHARACTER  MWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWM */
	/* MWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWM */
	/**
	 * The escape character
	 */
	const ESCAPE = "\033";

	/**
	 * The escape character
	 */
	const SIGNAL = self::ESCAPE;

	/* MWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWM */
	/* COLORS            MWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWM */
	/* MWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWM */
	/**
	 * Bold color red
	 */
	const BOLD_RED = "[1;31m";

	/**
	 * Bold color green
	 */
	const BOLD_GREEN = "[1;32m";

	/**
	 * Bold with color blue
	 */
	const BOLD_BLUE = "[1;34m";

	/**
	 * Bold color cyan
	 */
	const BOLD_CYAN = "[1;36m";

	/**
	 * Bold color yellow
	 */
	const BOLD_YELLOW = "[1;33m";

	/**
	 * Bold color magenta
	 */
	const BOLD_MAGENTA = "[1;35m";

	/**
	 * Bold color white
	 */
	const BOLD_WHITE = "[1;37m";

	/**
	 * Normal
	 */
	const NORMAL = "[0m";

	/**
	 * Color black
	 */
	const BLACK = "[0;30m";

	/**
	 * Color red
	 */
	const RED = "[0;31m";

	/**
	 * Color green
	 */
	const GREEN = "[0;32m";

	/**
	 * Color yellow
	 */
	const YELLOW = "[0;33m";

	/**
	 * Color blue
	 */
	const BLUE = "[0;34m";

	/**
	 * Color cyan
	 */
	const CYAN = "[0;36m";

	/**
	 * Color magenta
	 */
	const MAGENTA = "[0;35m";

	/**
	 * Color brown
	 */
	const BROWN = "[0;33m";

	/**
	 * Color gray
	 */
	const GRAY = "[0;37m";

	/**
	 * Bold
	 */
	const BOLD = "[1m";


	/* MWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWM */
	/* UNDERSCORE        MWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWM */
	/* MWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWM */
	/**
	 * Underscored
	 */
	const UNDERSCORE = "[4m";


	/* MWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWM */
	/* REVERSE           MWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWM */
	/* MWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWM */
	/**
	 * Reversed
	 */
	const REVERSE = "[7m";


	/* MWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWM */
	/* MACROS            MWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWM */
	/* MWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWM */
	/**
	 * Send a sequence to turn attributes off
	 */
	const SIGNAL_ATTRIBUTES_OFF = "\033[0m";


	/**
	 * Compiler instance
	 *
	 * @var \Cundd\Assetic\Plugin
	 */
	protected $compiler;

	/**
	 * Timestamp of the last re-compile
	 *
	 * @var integer
	 */
	protected $lastCompileTime;

	/**
	 * The configuration manager
	 *
	 * @var \TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface
	 * @inject
	 */
	protected $configurationManager;

	/**
	 * @var LiveReload
	 */
	protected $liveReloadServer;

	/**
	 * List of style file suffixes
	 *
	 * @var array
	 */
	protected $styleAssetSuffixes = array('less', 'scss', 'sass', 'css');

	/**
	 * List of script file suffixes
	 *
	 * @var array
	 */
	protected $scriptAssetSuffixes = array('js', 'coffee');

	/**
	 * List of other file suffixes that should trigger a full page reload
	 *
	 * @var array
	 */
	protected $otherAssetSuffixes = array('php', 'ts');

	/**
	 * Path to watch for changes
	 *
	 * @var string
	 */
	protected $watchPath = '';


	/**
	 * Run command
	 *
	 * @param string $destination Specify a relative path where the file should be copied to
	 * @param string $domainContext Specify the domain of the current context [Only used in multidomain installations]
	 */
	public function runCommand($destination = '', $domainContext = NULL) {
		$this->validateMultiDomainInstallation($domainContext);

		$sourcePath = $this->compile();
		if ($destination) {
			$this->copyToDestination($sourcePath, $destination);
		}
		$this->sendAndExit();
	}

	/**
	 * Automatically re-compiles the files if files in the directory path (or 'fileadmin/') changed
	 *
	 * @param integer $interval Interval between checks
	 * @param string $path Directory path that should be watched
	 * @param string $domainContext Specify the domain of the current context [Only used in multidomain installations]
	 */
	public function watchCommand($interval = 1, $path = 'fileadmin', $domainContext = NULL) {
		$this->watchPath = $path;
		$this->validateMultiDomainInstallation($domainContext);
		while (TRUE) {
			$this->recompileIfNeeded();
			sleep($interval);
		}
	}

	/**
	 * Start the LiveReload server and automatically re-compiles the files if
	 * files in the directory path (or 'fileadmin/') changed
	 *
	 * @param string  $address  IP to listen
	 * @param int     $port     Port to listen
	 * @param integer $interval Interval between checks
	 * @param string $path Directory path that should be watched
	 * @param string $domainContext Specify the domain of the current context [Only used in multidomain installations]
	 */
	public function liveReloadCommand($address = '0.0.0.0', $port = 35729, $interval = 1, $path = 'fileadmin', $domainContext = NULL) {
		$this->watchPath = $path;
		$this->validateMultiDomainInstallation($domainContext);

		$loop = LoopFactory::create();

		// Websocket server
		$this->liveReloadServer = new LiveReload();
		$socket = new ReactServer($loop);
		$socket->listen($port, $address);
		$server = new IoServer(
			new WsServer($this->liveReloadServer),
			$socket,
			$loop
		);

		$loop->addPeriodicTimer($interval, array($this, 'recompileIfNeededAndInformLiveReloadServer'));


		$this->outputLine(''
			. self::ESCAPE
			. self::GREEN
			. 'Websocket server listening on ' . $address . ':' . $port . ' running under PHP version ' . PHP_VERSION
			. self::ESCAPE
			. self::NORMAL
		);


		$loop->run();
	}

	/**
	 * Re-compiles the sources if needed and additionally informs the LiveReload server about the changes
	 */
	public function recompileIfNeededAndInformLiveReloadServer() {
		$fileNeedsRecompile = $this->needsRecompile();
		if (!$fileNeedsRecompile) {
			return;
		}

		$needFullPageReload = in_array(pathinfo($fileNeedsRecompile, PATHINFO_EXTENSION), array_merge($this->scriptAssetSuffixes, $this->otherAssetSuffixes));
		if ($needFullPageReload) {
			$this->liveReloadServer->fileDidChange($fileNeedsRecompile, FALSE);
		} else {
			$changedFile = $this->compile();
			$this->liveReloadServer->fileDidChange($changedFile);
		}
	}

	/**
	 * Re-compiles the sources if needed
	 */
	protected function recompileIfNeeded() {
		if ($this->needsRecompile()) {
			$this->compile();
		}
	}

	/**
	 * Compile the assets
	 *
	 * @return string
	 */
	protected function compile() {
		$outputFileLink = '';
		$compiler = $this->getCompiler();
		if ($compiler) {
			$compiler->forceCompile();
			$compiler->collectAssets();
			$compiler->clearHashCache();

			try {
				$outputFileLink = $compiler->compile();
				// $outputFileLink = $compiler->getOutputFilePath();
			} catch (\Exception $exception) {
				$this->handleException($exception);
//				$this->sendAndExit(1);
			}

			if ($compiler->getExperimental()) {
				$outputFileLink = $compiler->getSymlinkUri();
			}
		}
		$this->pd($compiler);
		return $outputFileLink;
	}

	/**
	 * Prints an error message if the installation is configured as multidomain, but no domainContext is specified
	 *
	 * @param mixed $domainContext
	 */
	protected function validateMultiDomainInstallation($domainContext) {
		if (ConfigurationUtility::isMultiDomain()) {

			$this->outputLine(self::SIGNAL . self::BOLD_RED . 'Multidomain installations are currently not supported' . self::SIGNAL_ATTRIBUTES_OFF);
			$this->sendAndExit(1);


			if (!$domainContext) {
				$this->handleException(new \UnexpectedValueException('This installation is configured as multidomain. Please specify the domainContext', 1408364616));
				$this->sendAndExit(1);
			}
			ConfigurationUtility::setDomainContext($domainContext);
			$this->outputLine(''
				. self::ESCAPE
				. self::GREEN
				. 'Switched to domain context ' . ConfigurationUtility::getDomainContext()
				. self::ESCAPE
				. self::NORMAL
			);
		}
	}

	/**
	 * Copies the source to the destination
	 *
	 * @param string $source
	 * @param string $destination
	 * @return string Returns the used path
	 */
	protected function copyToDestination($source, $destination) {
		if (!$destination) {
			return $source;
		}

		// Check if the filename has to be appended
		if (substr($destination, -1) === '/' || intval(strrpos($destination, '.')) < intval(strrpos($destination, '/'))) {
			if (substr($destination, -1) !== '/') {
				$destination .= '/';
			}
			$destination .= basename($source);
		}

		$destination = PATH_site . $destination;
		if (!file_exists(dirname($destination))) {
			mkdir(dirname($destination), 0775, TRUE);
		}
		if (copy($source, $destination)) {
			return $destination;
		}
		return $source;
	}

	/**
	 * Outputs specified text to the console window
	 * You can specify arguments that will be passed to the text via sprintf
	 *
	 * @see http://www.php.net/sprintf
	 * @param string $text      Text to output
	 * @param array  $arguments Optional arguments to use for sprintf
	 * @return void
	 */
	protected function output($text, array $arguments = array()) {
		if ($arguments !== array()) {
			$text = vsprintf($text, $arguments);
		}
		fwrite(STDOUT, $text);
		#$this->response->appendContent($text);
	}

	/**
	 * Outputs the specified text with color to the console window
	 *
	 * @param \Exception $exception
	 */
	protected function handleException($exception) {
		$heading = 'Exception: #' . $exception->getCode() . ':' . $exception->getMessage();
		$exceptionPosition = 'in ' . $exception->getFile() . ' at line ' . $exception->getLine();

		$coloredText = self::SIGNAL . self::REVERSE . self::SIGNAL . self::BOLD_RED . $heading . self::SIGNAL_ATTRIBUTES_OFF . PHP_EOL;
		$coloredText .= self::SIGNAL . self::BOLD_RED . $exceptionPosition . self::SIGNAL_ATTRIBUTES_OFF . PHP_EOL;
		$coloredText .= self::SIGNAL . self::RED . $exception->getTraceAsString() . self::SIGNAL_ATTRIBUTES_OFF . PHP_EOL;

		fwrite(STDOUT, $coloredText);
	}

	/**
	 * Returns a compiler instance with the configuration
	 *
	 * @return \Cundd\Assetic\Plugin
	 */
	public function getCompiler() {
		if (!$this->compiler) {
			$allConfiguration = $this->configurationManager->getConfiguration(ConfigurationManagerInterface::CONFIGURATION_TYPE_FULL_TYPOSCRIPT);
			if (isset($allConfiguration['plugin.']) && isset($allConfiguration['plugin.']['CunddAssetic.'])) {
				$configuration = $allConfiguration['plugin.']['CunddAssetic.'];
				$this->compiler = new \Cundd\Assetic\Plugin();
				$this->compiler->setConfiguration($configuration);
			}
		}
		return $this->compiler;
	}

	/**
	 * Returns all files with the given suffix under the given start directory
	 *
	 * @param string|array $suffix
	 * @param string       $startDirectory
	 * @return array<string>
	 */
	protected function findFilesBySuffix($suffix, $startDirectory) {
		$suffixPattern = '.{' . implode(',', (array)$suffix) . '}';
		if (substr($startDirectory, -1) !== '/') {
			$startDirectory .= '/';
		}
		$startDirectory .= '*';

		$foundFiles = glob($startDirectory . $suffixPattern, GLOB_BRACE);

		$i = 1;
		while ($i < 4) {
			$pattern = $startDirectory . str_repeat('*/*', $i) . $suffixPattern;
			$foundFiles = array_merge($foundFiles, glob($pattern, GLOB_BRACE));
			$i++;
		}
		return $foundFiles;
	}

	/**
	 * If a file changed it's path will be returned, otherwise FALSE
	 *
	 * @return string|bool
	 */
	protected function needsRecompile() {
		$lastCompileTime = $this->lastCompileTime;
		$assetSuffix = array_merge($this->scriptAssetSuffixes, $this->styleAssetSuffixes, $this->otherAssetSuffixes);
		$foundFiles = $this->findFilesBySuffix($assetSuffix, $this->watchPath);

		foreach ($foundFiles as $currentFile) {
			if (filemtime($currentFile) > $lastCompileTime) {
				$this->lastCompileTime = time();
				return $currentFile;
			}
		}
		return FALSE;
	}

	/**
	 * Dumps a given variable (or the given variables) wrapped into a 'pre' tag.
	 *
	 * @param    mixed $var1
	 * @return    string The printed content
	 */
	public function pd($var1 = '__iresults_pd_noValue') {
		if (class_exists('Tx_Iresults')) {
			$arguments = func_get_args();
			call_user_func_array(array('Tx_Iresults', 'pd'), $arguments);
		}
	}
}