<?php
declare(strict_types=1);

namespace Cundd\Assetic\Command;

use Cundd\Assetic\Configuration\ConfigurationProviderFactory;
use Cundd\Assetic\Configuration\ConfigurationProviderInterface;
use Cundd\Assetic\Exception\MissingConfigurationException;
use Cundd\Assetic\FileWatcher\FileWatcher;
use Cundd\Assetic\ManagerInterface;
use Cundd\Assetic\Utility\PathUtility;
use Cundd\Assetic\ValueObject\FilePath;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;
use function array_filter;
use function array_map;
use function basename;
use function copy;
use function count;
use function dirname;
use function explode;
use function file_exists;
use function implode;
use function intval;
use function mkdir;
use function strrpos;
use function substr;

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
     * @var ConfigurationProviderInterface
     */
    private $configurationProvider;

    /**
     * @param ManagerInterface             $manager
     * @param FileWatcher                  $fileWatcher
     * @param ConfigurationProviderFactory $configurationProviderFactory
     */
    public function __construct(
        ManagerInterface $manager,
        FileWatcher $fileWatcher,
        ConfigurationProviderFactory $configurationProviderFactory
    ) {
        parent::__construct();
        $this->manager = $manager;
        $this->fileWatcher = $fileWatcher;
        $this->configurationProvider = $configurationProviderFactory->build();
    }

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
     * @throws Throwable if an error occurred and `$graceful` is FALSE
     */
    protected function compile(bool $graceful): string
    {
        $this->manager->forceCompile();

        if (0 === count($this->manager->collectAssets()->all())) {
            throw new MissingConfigurationException('No assets have been found');
        }
        $outputFileLinkResult = $this->manager->forceCompile()->collectAndCompile();
        $this->manager->clearHashCache();
        if ($outputFileLinkResult->isErr()) {
            $exception = $outputFileLinkResult->unwrapErr();
            if (!$graceful) {
                throw $exception;
            }

            return '';
        }

        if ($this->getConfigurationProvider()->getCreateSymlink()) {
            return $this->manager->getSymlinkUri();
        }

        /** @var FilePath $filePath */
        $filePath = $outputFileLinkResult->unwrap();

        return $filePath->getPublicUri();
    }

    /**
     * Copy the source to the destination
     *
     * @param string $source
     * @param string $destination
     * @return string Returns the used path
     */
    protected function copyToDestination(string $source, string $destination): string
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
     * Output the specified text with color to the console window
     *
     * @param Throwable $exception
     */
    protected function handleException(Throwable $exception)
    {
        //$heading = 'Exception: #' . $exception->getCode() . ':' . $exception->getMessage();
        //$exceptionPosition = 'in ' . $exception->getFile() . ' at line ' . $exception->getLine();
        //
        //$coloredText = self::SIGNAL . self::REVERSE . self::SIGNAL . self::BOLD_RED . $heading . self::SIGNAL_ATTRIBUTES_OFF . PHP_EOL;
        //$coloredText .= self::SIGNAL . self::BOLD_RED . $exceptionPosition . self::SIGNAL_ATTRIBUTES_OFF . PHP_EOL;
        //$coloredText .= self::SIGNAL . self::RED
        //    . $exception->getTraceAsString()
        //    . self::SIGNAL_ATTRIBUTES_OFF . PHP_EOL;
        //
        //fwrite(STDOUT, $coloredText);
        //
        //if ($exception->getPrevious()) {
        //    $this->handleException($exception->getPrevious());
        //}
    }

    /**
     * Print the watched paths
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
     * If a file changed it's path will be returned, otherwise FALSE
     *
     * @return string|null
     */
    protected function needsRecompile(): ?string
    {
        return $this->getFileWatcher()->getChangedFileSinceLastCheck();
    }

    /**
     * @param string $path
     * @return string[]
     */
    protected function prepareWatchPaths(string $path): array
    {
        $watchPaths = array_filter(explode(',', $path));

        return array_map(
            [PathUtility::class, 'getAbsolutePath'],
            $watchPaths
        );
    }

    protected function getConfigurationProvider(): ConfigurationProviderInterface
    {
        return $this->configurationProvider;
    }
}
