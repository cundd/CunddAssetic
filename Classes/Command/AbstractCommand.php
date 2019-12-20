<?php
declare(strict_types=1);

namespace Cundd\Assetic\Command;

use Cundd\Assetic\Exception\MissingConfigurationException;
use Cundd\Assetic\FileWatcher\FileWatcher;
use Cundd\Assetic\Manager;
use Cundd\Assetic\ManagerInterface;
use Exception;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;
use TYPO3\CMS\Extbase\Object\ObjectManager;
use UnexpectedValueException;
use function class_exists;
use function implode;

/**
 * Command to compile, watch and start LiveReload
 */
abstract class AbstractCommand extends Command implements ColorInterface
{
    /**
     * Compiler instance
     *
     * @var ManagerInterface
     */
    protected $manager;

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
     * @var FileWatcher
     */
    private $fileWatcher;

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
            if ($manager->getExperimental()) {
                $outputFileLink = $manager->getSymlinkUri();
            }
            $manager->clearHashCache();

            return $outputFileLink;
        } catch (Exception $exception) {
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

        $destination = $this->getPublicPath() . '/' . $destination;
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
            $configurationManager = GeneralUtility::makeInstance(ObjectManager::class)->get(
                ConfigurationManagerInterface::class
            );

            $allConfiguration = $configurationManager->getConfiguration(
                ConfigurationManagerInterface::CONFIGURATION_TYPE_FULL_TYPOSCRIPT
            );
            if (isset($allConfiguration['plugin.']) && isset($allConfiguration['plugin.']['CunddAssetic.'])) {
                $configuration = $allConfiguration['plugin.']['CunddAssetic.'];
                $this->manager = new Manager($configuration);
            } else {
                throw new UnexpectedValueException('Could not read configuration for "plugin.CunddAssetic"');
            }
        }

        return $this->manager;
    }

    /**
     * If a file changed it's path will be returned, otherwise FALSE
     *
     * @return string|bool
     */
    protected function needsRecompile()
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

    /**
     * @return mixed
     */
    protected function getPublicPath()
    {
        if (class_exists(Environment::class, false)) {
            return Environment::getPublicPath();
        } else {
            return PATH_site;
        }
    }
}
