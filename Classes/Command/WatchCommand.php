<?php

declare(strict_types=1);

namespace Cundd\Assetic\Command;

use Cundd\Assetic\FileWatcher\FileWatcherInterface;
use Cundd\Assetic\Utility\Autoloader;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use function usleep;

/**
 * Command to watch and compile assets
 */
class WatchCommand extends AbstractWatchCommand
{
    /**
     * Configure the command by defining the name, options and arguments
     */
    public function configure()
    {
        $this
            ->setDescription('Watch and re-compile assets')
            ->setHelp('Automatically re-compiles the assets if files changed');
        $this->registerDefaultArgumentsAndOptions();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        Autoloader::register();

        $interval = $this->getInterval($input, 1);

        $fileWatcher = $this->getFileWatcher();
        $this->configureFileWatcherFromInput($input, $output, $fileWatcher);
        while (true) {
            $this->recompileIfNeeded($output, $fileWatcher);
            usleep((int) ($interval * 1000000));
        }
    }

    /**
     * Re-compiles the sources if needed
     */
    private function recompileIfNeeded(OutputInterface $output, FileWatcherInterface $fileWatcher)
    {
        $changedFile = $this->needsRecompile($fileWatcher);
        if (!$changedFile) {
            return;
        }
        // TODO: Handle the error
        $compiledFile = (string) $this->compile(true);

        $output->writeln("<info>File $changedFile has changed. Assets have been compiled into $compiledFile </info>");
    }
}
