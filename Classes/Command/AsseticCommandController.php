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

use Cundd\Assetic\FileWatcher\FileCategories;
use Cundd\Assetic\Manager;
use Cundd\Assetic\ManagerInterface;
use Cundd\Assetic\Plugin;
use Cundd\Assetic\Server\LiveReload;
use Cundd\Assetic\Utility\ConfigurationUtility;
use Cundd\CunddComposer\Autoloader;
use Ratchet\Server\IoServer;
use Ratchet\WebSocket\WsServer;
use React\EventLoop\Factory as LoopFactory;
use React\Socket\Server as ReactServer;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;
use TYPO3\CMS\Extbase\Mvc\Controller\CommandController;

Autoloader::register();

/**
 * Command to compile, watch and start LiveReload
 *
 * @package Cundd\Assetic\Command
 */
class AsseticCommandController extends CommandController implements ColorInterface
{
    /**
     * Compiler instance
     *
     * @var Plugin
     */
    protected $compiler;

    /**
     * The configuration manager
     *
     * @var \TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface
     * @inject
     */
    protected $configurationManager;

    /**
     * The file watcher
     *
     * @var \Cundd\Assetic\FileWatcher\FileWatcher
     * @inject
     */
    protected $fileWatcher;

    /**
     * @var LiveReload
     */
    protected $liveReloadServer;

    /**
     * Compiles the assets
     *
     * @param string $destination   Specify a relative path where the compiled file should be copied to
     * @param string $domainContext Specify the domain of the current context [Only used in multidomain installations]
     */
    public function compileCommand($destination = '', $domainContext = null)
    {
        $this->validateMultiDomainInstallation($domainContext);

        $usedPath = $sourcePath = $this->compile();
        if ($destination) {
            $usedPath = $this->copyToDestination($sourcePath, $destination);
        }

        $this->outputLine('Compiled assets and saved file to "%s"', array($usedPath));
        $this->sendAndExit();
    }

    /**
     * Automatically re-compiles the assets if files in path (or 'fileadmin/') changed
     *
     * @param integer $interval      Interval between checks
     * @param string  $path          Directory path(s) that should be watched (Multiple paths separated by comma ",")
     * @param string  $suffixes      File suffixes to watch for changes (separated by comma ",")
     * @param string  $domainContext Specify the domain of the current context [Only used in multidomain installations]
     * @param int     $maxDepth      Maximum directory depth of file to watch
     */
    public function watchCommand(
        $interval = 1,
        $path = 'fileadmin',
        $suffixes = '',
        $domainContext = null,
        $maxDepth = 7
    ) {
        $this->fileWatcher->setWatchPaths($this->prepareWatchPaths($path));
        $this->fileWatcher->setFindFilesMaxDepth(intval($maxDepth));
        if ($suffixes) {
            $this->fileWatcher->setAssetSuffixes(explode(',', $suffixes));
        }
        $this->printWatchedPaths();
        $this->validateMultiDomainInstallation($domainContext);
        while (true) {
            $this->recompileIfNeeded();
            sleep($interval);
        }
    }

    /**
     * Start the LiveReload server and watch for changes
     *
     * @param string  $address       IP to listen
     * @param int     $port          Port to listen
     * @param integer $interval      Interval between checks
     * @param string  $path          Directory path(s) that should be watched (Multiple paths separated by comma ",")
     * @param string  $suffixes      File suffixes to watch for changes (separated by comma ",")
     * @param string  $domainContext Specify the domain of the current context [Only used in multidomain installations]
     * @param int     $maxDepth      Maximum directory depth of file to watch
     * @throws \React\Socket\ConnectionException
     */
    public function liveReloadCommand(
        $address = '0.0.0.0',
        $port = 35729,
        $interval = -1,
        $path = 'fileadmin',
        $suffixes = '',
        $domainContext = null,
        $maxDepth = 7
    ) {
        $interval = $interval < 0 ? 0.5 : $interval;
        $this->fileWatcher->setWatchPaths($this->prepareWatchPaths($path));
        $this->fileWatcher->setFindFilesMaxDepth(intval($maxDepth));
        $this->fileWatcher->setInterval($interval);
        if ($suffixes) {
            $this->fileWatcher->setAssetSuffixes(explode(',', $suffixes));
        }
        $this->printWatchedPaths();
        $this->validateMultiDomainInstallation($domainContext);

        $loop = LoopFactory::create();

        // Websocket server
        $this->liveReloadServer = new LiveReload();
        $socket = new ReactServer($loop);
        $socket->listen($port, $address);
        new IoServer(
            new WsServer($this->liveReloadServer),
            $socket,
            $loop
        );

        $loop->addPeriodicTimer($interval, array($this, 'recompileIfNeededAndInformLiveReloadServer'));

        $this->outputLine(
            ''
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
    public function recompileIfNeededAndInformLiveReloadServer()
    {
        $fileNeedsRecompile = $this->needsRecompile();
        if (!$fileNeedsRecompile) {
            return;
        }

        $needFullPageReload = in_array(
            pathinfo($fileNeedsRecompile, PATHINFO_EXTENSION),
            array_merge(FileCategories::$scriptAssetSuffixes, FileCategories::$otherAssetSuffixes)
        );
        if ($needFullPageReload) {
            $this->liveReloadServer->fileDidChange($fileNeedsRecompile, false);
        } else {
            $changedFile = $this->compile();
            $this->liveReloadServer->fileDidChange($changedFile);
        }
    }

    /**
     * Re-compiles the sources if needed
     */
    protected function recompileIfNeeded()
    {
        if ($this->needsRecompile()) {
            $this->compile();
        }
    }

    /**
     * Compile the assets
     *
     * @return string
     */
    protected function compile()
    {
        $outputFileLink = '';
        $manager = $this->getManager();
        if ($manager) {
            $manager->forceCompile();
            try {
                $outputFileLink = $manager->collectAndCompile();
            } catch (\Exception $exception) {
                $this->handleException($exception);
            }
            if ($manager->getExperimental()) {
                $outputFileLink = $manager->getSymlinkUri();
            }
            $manager->clearHashCache();
        }

        return $outputFileLink;
    }

    /**
     * Prints an error message if the installation is configured as multidomain, but no domainContext is specified
     *
     * @param mixed $domainContext
     */
    protected function validateMultiDomainInstallation($domainContext)
    {
        if (ConfigurationUtility::isMultiDomain()) {
            $this->outputLine(
                self::SIGNAL . self::BOLD_RED . 'Multidomain installations are currently not supported' . self::SIGNAL_ATTRIBUTES_OFF
            );
            $this->sendAndExit(1);


            if (!$domainContext) {
                $this->handleException(
                    new \UnexpectedValueException(
                        'This installation is configured as multidomain. Please specify the domainContext',
                        1408364616
                    )
                );
                $this->sendAndExit(1);
            }
            ConfigurationUtility::setDomainContext($domainContext);
            $this->outputLine(
                ''
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
    protected function copyToDestination($source, $destination)
    {
        if (!$destination) {
            return $source;
        }

        // Check if the filename has to be appended
        if (substr($destination, -1) === '/' || intval(strrpos($destination, '.')) < intval(strrpos($destination, '/'))
        ) {
            if (substr($destination, -1) !== '/') {
                $destination .= '/';
            }
            $destination .= basename($source);
        }

        $destination = PATH_site . $destination;
        if (!file_exists(dirname($destination))) {
            mkdir(dirname($destination), 0775, true);
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
    protected function output($text, array $arguments = array())
    {
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
    protected function handleException($exception)
    {
        $heading = 'Exception: #' . $exception->getCode() . ':' . $exception->getMessage();
        $exceptionPosition = 'in ' . $exception->getFile() . ' at line ' . $exception->getLine();

        $coloredText = self::SIGNAL . self::REVERSE . self::SIGNAL . self::BOLD_RED . $heading . self::SIGNAL_ATTRIBUTES_OFF . PHP_EOL;
        $coloredText .= self::SIGNAL . self::BOLD_RED . $exceptionPosition . self::SIGNAL_ATTRIBUTES_OFF . PHP_EOL;
        $coloredText .= self::SIGNAL . self::RED . $exception->getTraceAsString(
            ) . self::SIGNAL_ATTRIBUTES_OFF . PHP_EOL;

        fwrite(STDOUT, $coloredText);
    }

    /**
     * Prints the watched paths
     */
    protected function printWatchedPaths()
    {
        $this->outputLine(
            ''
            . self::ESCAPE
            . self::GREEN
            . 'Watching path(s): ' . implode(', ', $this->fileWatcher->getWatchPaths())
            . self::ESCAPE
            . self::NORMAL
        );
    }

    /**
     * Returns a compiler instance with the configuration
     *
     * @return ManagerInterface
     */
    public function getManager()
    {
        if (!$this->compiler) {
            $allConfiguration = $this->configurationManager->getConfiguration(
                ConfigurationManagerInterface::CONFIGURATION_TYPE_FULL_TYPOSCRIPT
            );
            if (isset($allConfiguration['plugin.']) && isset($allConfiguration['plugin.']['CunddAssetic.'])) {
                $configuration = $allConfiguration['plugin.']['CunddAssetic.'];
                $this->compiler = new Manager($configuration);
            }
        }

        return $this->compiler;
    }

    /**
     * If a file changed it's path will be returned, otherwise FALSE
     *
     * @return string|bool
     */
    protected function needsRecompile()
    {
        return $this->fileWatcher->getChangedFileSinceLastCheck();
    }

    /**
     * @param $path
     * @return array
     */
    private function prepareWatchPaths($path)
    {
        // "Escape" the colon in "EXT:"
        $path = str_replace('EXT:', 'EXT;', $path);

        // Replace the colon with a comma
        $path = str_replace(':', ',', $path);
        $watchPaths = array_filter(explode(',', $path));

        return array_map(
            function ($path) {
                if (substr($path, 0, 4) === 'EXT;') {
                    return GeneralUtility::getFileAbsFileName('typo3conf/ext/' . substr($path, 4));
                }

                return $path;
            },
            $watchPaths
        );
    }
}
