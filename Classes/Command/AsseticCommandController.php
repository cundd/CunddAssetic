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
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;
use TYPO3\CMS\Extbase\Mvc\Controller\CommandController;

Autoloader::register();

/**
 * Command to compile, watch and start LiveReload
 *
 * @package Cundd\Assetic\Command
 */
class AsseticCommandController extends CommandController
{
    // MWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWM
    // ESCAPE CHARACTER
    // MWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWM
    /**
     * The escape character
     */
    const ESCAPE = "\033";

    /**
     * The escape character
     */
    const SIGNAL = self::ESCAPE;

    // MWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWM
    // COLORS
    // MWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWM
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


    // MWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWM
    // UNDERSCORE
    // MWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWM
    /**
     * Underscored
     */
    const UNDERSCORE = "[4m";


    // MWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWM
    // REVERSE
    // MWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWM
    /**
     * Reversed
     */
    const REVERSE = "[7m";


    // MWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWM
    // MACROS
    // MWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWMWM
    /**
     * Send a sequence to turn attributes off
     */
    const SIGNAL_ATTRIBUTES_OFF = "\033[0m";


    /**
     * Compiler instance
     *
     * @var Plugin
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
    protected $otherAssetSuffixes = array('php', 'ts', 'html');

    /**
     * Array of paths to watch for changes
     *
     * @var string[]
     */
    protected $watchPaths = array();

    /**
     * Max depth to collect files for
     *
     * @var int
     */
    protected $findFilesMaxDepth = 7;

    /**
     * Array of watched files
     *
     * @var string[]
     */
    protected $watchedFilesCache = array();

    /**
     * Timestamp of the last directory scan
     *
     * @var int
     */
    protected $watchedFilesCacheTime = 0;

    /**
     * Lifetime of the directory scan cache
     *
     * @var int
     */
    protected $watchedFilesCacheLifetime = 5;


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
     * @param string  $path          Directory path(s) that should be watched (Multiple paths separated by colon ":")
     * @param string  $domainContext Specify the domain of the current context [Only used in multidomain installations]
     */
    public function watchCommand($interval = 1, $path = 'fileadmin', $domainContext = null)
    {
        $this->watchPaths = explode(':', $path);
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
     * @param string  $path          path(s) that should be watched (Multiple paths separated by colon ":")
     * @param string  $domainContext Specify the domain of the current context [Only used in multidomain installations]
     */
    public function liveReloadCommand(
        $address = '0.0.0.0',
        $port = 35729,
        $interval = 1,
        $path = 'fileadmin',
        $domainContext = null
    ) {
        $this->watchPaths = explode(':', $path);
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
            .self::ESCAPE
            .self::GREEN
            .'Websocket server listening on '.$address.':'.$port.' running under PHP version '.PHP_VERSION
            .self::ESCAPE
            .self::NORMAL
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
            array_merge($this->scriptAssetSuffixes, $this->otherAssetSuffixes)
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
                $manager->collectAndCompile();
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
                self::SIGNAL.self::BOLD_RED.'Multidomain installations are currently not supported'.self::SIGNAL_ATTRIBUTES_OFF
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
                .self::ESCAPE
                .self::GREEN
                .'Switched to domain context '.ConfigurationUtility::getDomainContext()
                .self::ESCAPE
                .self::NORMAL
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

        $destination = PATH_site.$destination;
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
        $heading = 'Exception: #'.$exception->getCode().':'.$exception->getMessage();
        $exceptionPosition = 'in '.$exception->getFile().' at line '.$exception->getLine();

        $coloredText = self::SIGNAL.self::REVERSE.self::SIGNAL.self::BOLD_RED.$heading.self::SIGNAL_ATTRIBUTES_OFF.PHP_EOL;
        $coloredText .= self::SIGNAL.self::BOLD_RED.$exceptionPosition.self::SIGNAL_ATTRIBUTES_OFF.PHP_EOL;
        $coloredText .= self::SIGNAL.self::RED.$exception->getTraceAsString().self::SIGNAL_ATTRIBUTES_OFF.PHP_EOL;

        fwrite(STDOUT, $coloredText);
    }

    /**
     * Prints the watched paths
     */
    protected function printWatchedPaths()
    {
        $this->outputLine(
            ''
            .self::ESCAPE
            .self::GREEN
            .'Watching path(s): '.implode(', ', $this->watchPaths)
            .self::ESCAPE
            .self::NORMAL
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
     * Returns all files with the given suffix under the given start directory
     *
     * @param string|string[] $suffix
     * @param string          $startDirectory
     * @return string[]
     */
    protected function findFilesBySuffix($suffix, $startDirectory)
    {
        if (!defined('GLOB_BRACE')) {
            return $this->findFilesBySuffixWithoutGlobBrace($suffix, $startDirectory);
        }

        return $this->findFilesBySuffixWithGlobBrace($suffix, $startDirectory);
    }

    /**
     * If a file changed it's path will be returned, otherwise FALSE
     *
     * @return string|bool
     */
    protected function needsRecompile()
    {
        $lastCompileTime = $this->lastCompileTime;
        $foundFiles = $this->collectFilesToWatch();

        foreach ($foundFiles as $currentFile) {
            if (filemtime($currentFile) > $lastCompileTime) {
                $this->lastCompileTime = time();

                return $currentFile;
            }
        }

        return false;
    }

    /**
     * Returns the files that are watched
     *
     * string[]
     */
    protected function collectFilesToWatch()
    {
        $currentTime = time();
        if (($currentTime - $this->watchedFilesCacheTime) > $this->watchedFilesCacheLifetime) {

            $assetSuffix = array_merge(
                $this->scriptAssetSuffixes,
                $this->styleAssetSuffixes,
                $this->otherAssetSuffixes
            );
            $foundFiles = array();

            foreach ($this->watchPaths as $currentWatchPath) {
                $foundFilesForCurrentPath = $this->findFilesBySuffix($assetSuffix, $currentWatchPath);
                if ($foundFilesForCurrentPath) {
                    $foundFiles = array_merge($foundFiles, $foundFilesForCurrentPath);
                }
            }

            $this->watchedFilesCacheTime = $currentTime;
            $this->watchedFilesCache = $foundFiles;
        }

        return $this->watchedFilesCache;
    }

    /**
     * Returns all files with the given suffix under the given start directory
     *
     * @param string|string[] $suffix
     * @param string          $startDirectory
     * @return string[]
     */
    private function findFilesBySuffixWithoutGlobBrace($suffix, $startDirectory)
    {
        $foundFiles = array();
        if (is_array($suffix)) {
            foreach ($suffix as $currentSuffix) {
                $foundFiles = array_merge(
                    $foundFiles,
                    $this->findFilesBySuffixWithoutGlobBrace($currentSuffix, $startDirectory)
                );
            }

            return $foundFiles;
        } elseif (!is_string($suffix)) {
            throw new \InvalidArgumentException(
                sprintf('Expected argument suffix to be of type string, %s given', gettype($suffix)),
                1453993530
            );
        }

        $maxDepth = $this->findFilesMaxDepth;
        $startDirectory = rtrim($startDirectory, '/').'/';

        $pathPattern = $startDirectory.'*.'.$suffix;
        $foundFiles = glob($pathPattern);

        $i = 1;
        while ($i < $maxDepth) {
            $pattern = $startDirectory.str_repeat('*/*', $i).'.'.$suffix;
            $foundFiles = array_merge($foundFiles, glob($pattern));
            $i++;
        }

        return $foundFiles;
    }

    /**
     * Returns all files with the given suffix under the given start directory
     *
     * @param string|string[] $suffix
     * @param string          $startDirectory
     * @return string[]
     */
    private function findFilesBySuffixWithGlobBrace($suffix, $startDirectory)
    {
        $maxDepth = $this->findFilesMaxDepth;
        $suffixPattern = '.{'.implode(',', (array)$suffix).'}';
        $startDirectory = rtrim($startDirectory, '/').'/*';

        $foundFiles = glob($startDirectory.$suffixPattern, GLOB_BRACE);

        $i = 1;
        while ($i < $maxDepth) {
            $pattern = $startDirectory.str_repeat('*/*', $i).$suffixPattern;
            $foundFiles = array_merge($foundFiles, glob($pattern, GLOB_BRACE));
            $i++;
        }

        return $foundFiles;
    }
}
