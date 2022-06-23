<?php
declare(strict_types=1);

namespace Cundd\Assetic\Command;

use Cundd\CunddComposer\Autoloader;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use function explode;
use function sleep;

/**
 * Command to watch and compile assets
 */
class WatchCommand extends AbstractCommand implements ColorInterface
{
    /**
     * Configure the command by defining the name, options and arguments
     */
    public function configure()
    {
        $this
            ->setDescription('Watch and re-compile assets')
            ->setHelp('Automatically re-compiles the assets if files changed')
            ->addOption(
                'interval',
                'i',
                InputOption::VALUE_REQUIRED,
                'Interval between checks',
                1
            )
            ->addOption(
                'path',
                'p',
                InputOption::VALUE_REQUIRED,
                'Directory path(s) that should be watched (separated by comma ",")',
                'fileadmin,EXT:client'
            )
            ->addOption(
                'suffixes',
                's',
                InputOption::VALUE_REQUIRED,
                'File suffixes to watch for changes (separated by comma ",")',
                ''
            )
            ->addOption(
                'max-depth',
                'm',
                InputOption::VALUE_REQUIRED,
                'Maximum directory depth of file to watch',
                7
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        Autoloader::register();

        $interval = max((int)$input->getOption('interval'), 1);
        $path = $input->getOption('path');
        $suffixes = $input->getOption('suffixes');
        $maxDepth = (int)$input->getOption('max-depth');

        $fileWatcher = $this->getFileWatcher();
        $fileWatcher->setWatchPaths($this->prepareWatchPaths($path));
        $fileWatcher->setFindFilesMaxDepth($maxDepth);
        if ($suffixes) {
            $fileWatcher->setAssetSuffixes(explode(',', $suffixes));
        }
        $this->printWatchedPaths($output);
        while (true) {
            $this->recompileIfNeeded($output);
            sleep($interval);
        }
    }

    /**
     * Re-compiles the sources if needed
     *
     * @param OutputInterface $output
     */
    private function recompileIfNeeded(OutputInterface $output)
    {
        $changedFile = $this->needsRecompile();
        if (!$changedFile) {
            return;
        }
        $compiledFile = $this->compile(true);

        $output->writeln("<info>File $changedFile has changed. Assets have been compiled into $compiledFile </info>");
    }
}
