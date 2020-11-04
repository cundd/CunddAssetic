<?php
declare(strict_types=1);

namespace Cundd\Assetic\Command;

use Cundd\Assetic\Configuration\ConfigurationProvider;
use Cundd\Assetic\Exception\MissingConfigurationException;
use Cundd\Assetic\FileWatcher\FileWatcher;
use Cundd\Assetic\Manager;
use Cundd\Assetic\ManagerInterface;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;
use TYPO3\CMS\Extbase\Object\ObjectManager;
use UnexpectedValueException;
use function array_filter;
use function array_map;
use function basename;
use function copy;
use function count;
use function dirname;
use function explode;
use function file_exists;
use function fwrite;
use function implode;
use function intval;
use function mkdir;
use function str_replace;
use function strrpos;
use function substr;
use function vsprintf;
use const PHP_EOL;
use const STDOUT;

/**
 * Command to compile, watch and start LiveReload
 */
abstract class AbstractCommand extends Command implements ColorInterface
{
    /**
     * Compiler Manager instance
     *
     * @var ManagerInterface
     */
    private $manager;

    /**
     * The file watcher
     *
     * @var FileWatcher
     */
    private $fileWatcher;

    /**
     * @var ConfigurationProvider
     */
    private $configurationProvider;

    /**
     * @return FileWatcher
     */
    protected function getFileWatcher(): FileWatcher
    {
        if (!$this->fileWatcher) {
            $this->fileWatcher = new FileWatcher();
        }

        return $this->fileWatcher;
    }

    /**
     * Compile the assets
     *
     * @param bool $graceful
     * @return string
     */
    protected function compile(bool $graceful): string
    {
        $manager = $this->getManager();
        $manager->forceCompile();

        if (0 === count($manager->collectAssets()->all())) {
            throw new MissingConfigurationException('No assets have been found');
        }
        try {
            $outputFileLink = $manager->collectAndCompile();
            if ($this->getConfigurationProvider()->getCreateSymlink()) {
                $outputFileLink = $manager->getSymlinkUri();
            }
            $manager->clearHashCache();

            return $outputFileLink;
        } catch (Throwable $exception) {
            $this->handleException($exception);

            if (!$graceful) {
                throw new RuntimeException($exception->getMessage(), $exception->getCode(), $exception);
            }
        }

        return '';
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

        $destination = $this->getConfigurationProvider()->getPublicPath() . '/' . $destination;
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
     *
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
    }

    /**
     * Outputs the specified text with color to the console window
     *
     * @param Throwable $exception
     */
    protected function handleException(Throwable $exception)
    {
        $heading = 'Exception: #' . $exception->getCode() . ':' . $exception->getMessage();
        $exceptionPosition = 'in ' . $exception->getFile() . ' at line ' . $exception->getLine();

        $coloredText = self::SIGNAL . self::REVERSE . self::SIGNAL . self::BOLD_RED . $heading . self::SIGNAL_ATTRIBUTES_OFF . PHP_EOL;
        $coloredText .= self::SIGNAL . self::BOLD_RED . $exceptionPosition . self::SIGNAL_ATTRIBUTES_OFF . PHP_EOL;
        $coloredText .= self::SIGNAL . self::RED
            . $exception->getTraceAsString()
            . self::SIGNAL_ATTRIBUTES_OFF . PHP_EOL;

        fwrite(STDOUT, $coloredText);

        if ($exception->getPrevious()) {
            $this->handleException($exception->getPrevious());
        }
    }

    /**
     * Prints the watched paths
     *
     * @param OutputInterface $output
     */
    protected function printWatchedPaths(OutputInterface $output)
    {
        $output->writeln(
            '<info>' . 'Watching path(s): ' . implode(', ', $this->fileWatcher->getWatchPaths()) . '</info>'
        );
    }

    /**
     * Returns a compiler instance with the configuration
     *
     * @return ManagerInterface
     */
    public function getManager()
    {
        if (!$this->manager) {
            $this->manager = new Manager($this->getConfigurationProvider());
        }

        return $this->manager;
    }

    /**
     * If a file changed it's path will be returned, otherwise FALSE
     *
     * @return string|null
     */
    protected function needsRecompile():?string
    {
        return $this->getFileWatcher()->getChangedFileSinceLastCheck();
    }

    /**
     * @param $path
     * @return array
     */
    protected function prepareWatchPaths($path)
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

    protected function getConfigurationProvider(): ConfigurationProvider
    {
        if (!$this->configurationProvider) {
            $configurationManager = GeneralUtility::makeInstance(ObjectManager::class)->get(
                ConfigurationManagerInterface::class
            );

            $allConfiguration = $configurationManager->getConfiguration(
                ConfigurationManagerInterface::CONFIGURATION_TYPE_FULL_TYPOSCRIPT
            );
            if (isset($allConfiguration['plugin.']) && isset($allConfiguration['plugin.']['CunddAssetic.'])) {
                $configuration = $allConfiguration['plugin.']['CunddAssetic.'];
                $this->configurationProvider = new ConfigurationProvider($configuration);
            } else {
                throw new UnexpectedValueException('Could not read configuration for "plugin.CunddAssetic"');
            }
        }

        return $this->configurationProvider;
    }
}
