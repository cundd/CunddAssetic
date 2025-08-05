<?php

declare(strict_types=1);

namespace Cundd\Assetic\Command;

use Cundd\Assetic\Command\Input\ArrayUtility;
use Cundd\Assetic\Command\Input\WatchPathsBuilder;
use Cundd\Assetic\Configuration\ConfigurationProviderFactory;
use Cundd\Assetic\FileWatcher\FileWatcher;
use Cundd\Assetic\FileWatcher\FileWatcherInterface;
use Cundd\Assetic\ManagerInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

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

    private FileWatcherInterface $fileWatcher;

    public function __construct(
        ManagerInterface $manager,
        FileWatcher $fileWatcher,
        ConfigurationProviderFactory $configurationProviderFactory,
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
                InputOption::VALUE_IS_ARRAY | InputOption::VALUE_REQUIRED,
                'Directory path(s) that should be watched',
                ['EXT:client']
            )
            ->addOption(
                self::OPTION_SUFFIXES,
                's',
                InputOption::VALUE_IS_ARRAY | InputOption::VALUE_REQUIRED,
                'File suffixes to watch for changes'
            )
            ->addOption(
                self::OPTION_MAX_DEPTH,
                'm',
                InputOption::VALUE_REQUIRED,
                'Maximum directory depth of file to watch',
                7
            );
    }

    protected function getFileWatcher(): FileWatcherInterface
    {
        return $this->fileWatcher;
    }

    protected function getInterval(InputInterface $input, float $min): float
    {
        return max((float) (int) $input->getOption(self::OPTION_INTERVAL), $min);
    }

    protected function configureFileWatcherFromInput(
        InputInterface $input,
        OutputInterface $output,
        FileWatcherInterface $fileWatcher,
    ): FileWatcherInterface {
        $paths = (new WatchPathsBuilder())->buildPathsFromInput($input, self::PATHS);
        $suffixes = $input->getOption(self::OPTION_SUFFIXES);
        $maxDepth = (int) $input->getOption(self::OPTION_MAX_DEPTH);

        $this->configureFileWatcher($fileWatcher, $paths, $maxDepth, $suffixes);
        $this->printWatchedPaths($output, $fileWatcher);

        return $fileWatcher;
    }

    /**
     * @param array<non-empty-string> $paths
     * @param array<non-empty-string> $suffixes
     */
    protected function configureFileWatcher(
        FileWatcherInterface $fileWatcher,
        array $paths,
        int $maxDepth,
        array $suffixes,
    ): FileWatcherInterface {
        $fileWatcher->setWatchPaths($paths);
        if ($fileWatcher instanceof FileWatcher) {
            $fileWatcher->setFindFilesMaxDepth($maxDepth);
        }
        if ($suffixes) {
            $fileWatcher->setAssetSuffixes(ArrayUtility::normalizeInput($suffixes));
        }

        return $fileWatcher;
    }

    /**
     * Print the watched paths
     */
    protected function printWatchedPaths(
        OutputInterface $output,
        FileWatcherInterface $fileWatcher,
    ): void {
        $output->writeln(
            '<info>Watching path(s): ' . implode(', ', $fileWatcher->getWatchPaths()) . '</info>'
        );
    }

    /**
     * If a file changed its path will be returned, otherwise FALSE
     */
    protected function needsRecompile(FileWatcherInterface $fileWatcher): ?string
    {
        return $fileWatcher->getChangedFileSinceLastCheck();
    }
}
