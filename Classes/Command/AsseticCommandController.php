<?php

namespace Cundd\Assetic\Command;

use Cundd\Assetic\Exception\MissingConfigurationException;
use Cundd\Assetic\FileWatcher\FileCategories;
use Cundd\Assetic\Manager;
use Cundd\Assetic\ManagerInterface;
use Cundd\Assetic\Plugin;
use Cundd\Assetic\Server\LiveReload;
use Cundd\Assetic\Utility\ConfigurationUtility;
use Cundd\CunddComposer\Autoloader;
use Ratchet\Http\HttpServer;
use Ratchet\Server\IoServer;
use Ratchet\WebSocket\WsServer;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;
use TYPO3\CMS\Extbase\Mvc\Controller\CommandController;

Autoloader::register();

/**
 * Command to compile, watch and start LiveReload
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

        $usedPath = $sourcePath = $this->compile(false);
        if ($destination) {
            $usedPath = $this->copyToDestination($sourcePath, $destination);
        }

        $this->outputLine('Compiled assets and saved file to "%s"', [$usedPath]);
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
     * @param string  $address           IP to listen
     * @param int     $port              Port to listen
     * @param integer $interval          Interval between checks
     * @param string  $path              Directory path(s) that should be watched (Multiple paths separated by comma ",")
     * @param string  $suffixes          File suffixes to watch for changes (separated by comma ",")
     * @param string  $domainContext     Specify the domain of the current context [Only used in multidomain installations]
     * @param int     $maxDepth          Maximum directory depth of file to watch
     * @param float   $notificationDelay Number of seconds to wait before sending the reload command to the clients
     */
    public function liveReloadCommand(
        $address = '0.0.0.0',
        $port = 35729,
        $interval = -1,
        $path = 'fileadmin',
        $suffixes = '',
        $domainContext = null,
        $maxDepth = 7,
        $notificationDelay = 0.0
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

        // Websocket server
        $this->liveReloadServer = new LiveReload($notificationDelay);
        $server = IoServer::factory(
            new HttpServer(
                new WsServer(
                    $this->liveReloadServer
                )
            ),
            $port,
            $address
        );

        $server->loop->addPeriodicTimer($interval, [$this, 'recompileIfNeededAndInformLiveReloadServer']);
        $this->liveReloadServer->setEventLoop($server->loop);
        $this->outputLine(
            ''
            . self::ESCAPE
            . self::GREEN
            . 'Websocket server listening on ' . $address . ':' . $port . ' running under PHP version ' . PHP_VERSION
            . self::ESCAPE
            . self::NORMAL
        );

        $server->run();
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
            $changedFile = $this->compile(true);
            $this->liveReloadServer->fileDidChange($changedFile);
        }
    }

    /**
     * Re-compiles the sources if needed
     */
    protected function recompileIfNeeded()
    {
        $changedFile = $this->needsRecompile();
        if (!$changedFile) {
            return;
        }
        $compiledFile = $this->compile(true);

        $this->outputLine(
            ''
            . self::ESCAPE
            . self::GREEN
            . 'File ' . $changedFile . ' has changed. Assets have been compiled into ' . $compiledFile
            . self::ESCAPE
            . self::NORMAL
        );
    }

    /**
     * Compile the assets
     *
     * @param bool $graceful
     * @return string
     * @throws \Exception
     */
    protected function compile($graceful)
    {
        $manager = $this->getManager();
        $manager->forceCompile();

        if (0 === count($manager->collectAssets()->all())) {
            throw new MissingConfigurationException('No assets have been found');
        }
        try {
            $outputFileLink = $manager->collectAndCompile();
            if ($manager->getExperimental()) {
                $outputFileLink = $manager->getSymlinkUri();
            }
            $manager->clearHashCache();

            return $outputFileLink;
        } catch (\Exception $exception) {
            $this->handleException($exception);

            if (!$graceful) {
                throw $exception;
            }
        }

        return '';
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
    protected function output($text, array $arguments = [])
    {
        if ($arguments !== []) {
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
        $coloredText .= self::SIGNAL . self::RED
            . $exception->getTraceAsString()
            . self::SIGNAL_ATTRIBUTES_OFF . PHP_EOL;

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
            } else {
                throw new \UnexpectedValueException('Could not read configuration for "plugin.CunddAssetic"');
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
