<?php
declare(strict_types=1);

namespace Cundd\Assetic\Command;

use Cundd\Assetic\Configuration\ConfigurationProviderFactory;
use Cundd\Assetic\FileWatcher\FileWatcher;
use Cundd\Assetic\FileWatcher\FileWatcherInterface;
use Cundd\Assetic\ManagerInterface;
use Cundd\Assetic\Utility\PathUtility;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use function array_filter;
use function array_map;
use function explode;
use function implode;
use function max;

/**
 * Command to compile, watch and start LiveReload
 */
abstract class AbstractWatchCommand extends AbstractCommand
{
    private const PATHS = 'paths';
    private const OPTION_INTERVAL = 'interval';
    private const OPTION_SUFFIXES = 'suffixes';
    private const OPTION_MAX_DEPTH = 'max-depth';

    private FileWatcher $fileWatcher;

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
        parent::__construct($manager, $configurationProviderFactory);
        $this->fileWatcher = $fileWatcher;
    }

    protected function registerDefaultArgumentsAndOptions(): self
    {
        return $this
            ->addArgument(
                self::PATHS,
                InputArgument::IS_ARRAY,
                'Directory path(s) that should be watched'
            )
            ->addOption(
                self::OPTION_INTERVAL,
                'i',
                InputOption::VALUE_REQUIRED,
                'Interval between checks',
                1
            )
            ->addOption(
                self::PATHS,
                'p',
                InputOption::VALUE_REQUIRED,
                'Directory path(s) that should be watched (separated by comma ",")',
                'fileadmin,EXT:client'
            )
            ->addOption(
                self::OPTION_SUFFIXES,
                's',
                InputOption::VALUE_REQUIRED,
                'File suffixes to watch for changes (separated by comma ",")',
                ''
            )
            ->addOption(
                self::OPTION_MAX_DEPTH,
                'm',
                InputOption::VALUE_REQUIRED,
                'Maximum directory depth of file to watch',
                7
            );
    }

    /**
     * @return FileWatcher
     */
    protected function getFileWatcher(): FileWatcher
    {
        return $this->fileWatcher;
    }

    protected function getInterval(InputInterface $input, float $min): float
    {
        return max((float)(int)$input->getOption(self::OPTION_INTERVAL), $min);
    }

    protected function configureFileWatcherFromInput(
        InputInterface $input,
        OutputInterface $output,
        FileWatcherInterface $fileWatcher
    ): FileWatcherInterface {
        $paths = $this->getPaths($input);
        $suffixes = $input->getOption(self::OPTION_SUFFIXES);
        $maxDepth = (int)$input->getOption(self::OPTION_MAX_DEPTH);

        $this->configureFileWatcher($fileWatcher, $paths, $maxDepth, $suffixes);
        $this->printWatchedPaths($output, $fileWatcher);

        return $fileWatcher;
    }

    protected function configureFileWatcher(
        FileWatcherInterface $fileWatcher,
        array $paths,
        int $maxDepth,
        mixed $suffixes
    ): FileWatcherInterface {
        $fileWatcher->setWatchPaths($this->prepareWatchPaths($paths));
        $fileWatcher->setFindFilesMaxDepth($maxDepth);
        if ($suffixes) {
            $fileWatcher->setAssetSuffixes(explode(',', $suffixes));
        }

        return $fileWatcher;
    }

    /**
     * @param InputInterface $input
     * @return string[]
     */
    private function getPaths(InputInterface $input): array
    {
        $argument = $input->getArgument(self::PATHS);
        if ($argument) {
            return $argument;
        }

        return explode(',', $input->getOption(self::PATHS));
    }

    /**
     * Print the watched paths
     *
     * @param OutputInterface      $output
     * @param FileWatcherInterface $fileWatcher
     */
    protected function printWatchedPaths(OutputInterface $output, FileWatcherInterface $fileWatcher)
    {
        $output->writeln(
            '<info>' . 'Watching path(s): ' . implode(', ', $fileWatcher->getWatchPaths()) . '</info>'
        );
    }

    /**
     * If a file changed its path will be returned, otherwise FALSE
     *
     * @return string|null
     */
    protected function needsRecompile(FileWatcherInterface $fileWatcher): ?string
    {
        return $fileWatcher->getChangedFileSinceLastCheck();
    }

    /**
     * @param string[] $paths
     * @return string[]
     */
    private function prepareWatchPaths(array $paths): array
    {
        return array_map(
            [PathUtility::class, 'getAbsolutePath'],
            array_filter($paths)
        );
    }
}
