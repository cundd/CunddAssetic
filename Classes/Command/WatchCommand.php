<?php

declare(strict_types=1);

namespace Cundd\Assetic\Command;

use Cundd\Assetic\FileWatcher\FileWatcherInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

use function usleep;

/**
 * Command to watch and compile assets
 */
class WatchCommand extends AbstractWatchCommand
{
    /**
     * Configure the command by defining the name, options and arguments
     */
    public function configure(): void
    {
        $this
            ->setDescription('Watch and re-compile assets')
            ->setHelp('Automatically re-compiles the assets if files changed');
        $this->registerDefaultArgumentsAndOptions();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $interval = $this->getInterval($input, 1);

        $fileWatcher = $this->getFileWatcher();
        $this->configureFileWatcherFromInput($input, $output, $fileWatcher);
        while (true) { // @phpstan-ignore while.alwaysTrue
            $this->recompileIfNeeded($output, $fileWatcher);
            usleep((int) ($interval * 1000000));
        }
    }

    /**
     * Re-compiles the sources if needed
     */
    private function recompileIfNeeded(OutputInterface $output, FileWatcherInterface $fileWatcher): void
    {
        $changedFile = $this->needsRecompile($fileWatcher);
        if (!$changedFile) {
            return;
        }

        $result = $this->compile();
        if ($result->isErr()) {
            /** @var Throwable $error */
            $error = $result->unwrapErr();
            $output->writeln("<error>File $changedFile has changed. But compilation failed: {$error->getMessage()} </error>");
        } else {
            $compiledFile = $result->unwrap()->getPublicUri();

            $output->writeln("<info>File $changedFile has changed. Assets have been compiled into $compiledFile </info>");
        }
    }
}
