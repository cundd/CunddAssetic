<?php

declare(strict_types=1);

namespace Cundd\Assetic\Command;

use Cundd\Assetic\BuildSystem\ExecutorInterface;
use Cundd\Assetic\Configuration;
use Cundd\Assetic\Configuration\ConfigurationFactory;
use Cundd\Assetic\FileWatcher\FileWatcherInterface;
use Cundd\Assetic\Output\CacheManagerInterface as OutputCacheManagerInterface;
use Cundd\Assetic\ValueObject\CompilationContext;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;
use TYPO3\CMS\Core\Cache\CacheManager as TYPO3CacheManager;
use TYPO3\CMS\Core\Site\SiteFinder;

use function usleep;

/**
 * Command to watch and compile assets
 */
final class WatchCommand extends AbstractWatchCommand
{
    use ClearPageCacheTrait;

    public function __construct(
        ExecutorInterface $executor,
        ConfigurationFactory $configurationFactory,
        SiteFinder $siteFinder,
        FileWatcherInterface $fileWatcher,
        OutputCacheManagerInterface $outputCacheManager,
        private readonly TYPO3CacheManager $pageCacheManager,
    ) {
        parent::__construct(
            $executor,
            $configurationFactory,
            $siteFinder,
            $outputCacheManager,
            $fileWatcher,
        );
    }

    /**
     * Configure the command by defining the name, options and arguments
     */
    public function configure(): void
    {
        $this
            ->setDescription('Watch and re-compile assets')
            ->setHelp('Automatically re-compiles the assets if files changed');

        $this->registerDefaultArgumentsAndOptions()
            ->addOption(
                'clear-page-cache',
                'x',
                InputOption::VALUE_NONE,
                'Clear the page cache after compilation'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): never
    {
        $compilationContext = $this->getCompilationContext($input);
        $configuration = $this->getConfiguration($compilationContext);
        $interval = $this->getInterval($input, 1);
        $clearPageCache = (bool) $input->getOption('clear-page-cache');

        $this->configureFileWatcherFromInput(
            $input,
            $output,
            $this->fileWatcher
        );
        while (true) {
            $didRecompile = $this->recompileIfNeeded(
                $configuration,
                $compilationContext,
                $output,
                $this->fileWatcher
            );
            if ($didRecompile && $clearPageCache) {
                $this->tryClearPageCache($this->pageCacheManager, $output);
            }
            usleep((int) ($interval * 1000000));
        }
    }

    /**
     * Re-compiles the sources if needed
     */
    private function recompileIfNeeded(
        Configuration $configuration,
        CompilationContext $compilationContext,
        OutputInterface $output,
        FileWatcherInterface $fileWatcher,
    ): bool {
        $changedFile = $this->needsRecompile($fileWatcher);
        if (!$changedFile) {
            return false;
        }

        $result = $this->compile($configuration, $compilationContext);
        if ($result->isErr()) {
            /** @var Throwable $error */
            $error = $result->unwrapErr();
            $output->writeln("<error>File $changedFile has changed. But compilation failed: {$error->getMessage()} </error>");
        } else {
            $compiledFile = $result->unwrap()->getPublicUri();

            $output->writeln("<info>File $changedFile has changed. Assets have been compiled into $compiledFile </info>");
        }

        return true;
    }
}
