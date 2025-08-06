<?php

declare(strict_types=1);

namespace Cundd\Assetic\Command;

use Cundd\Assetic\Configuration\ConfigurationProviderFactory;
use Cundd\Assetic\ManagerInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use TYPO3\CMS\Core\Cache\CacheManager;

use function sprintf;

/**
 * Command to compile assets
 */
class CompileCommand extends AbstractCommand
{
    public function __construct(
        ManagerInterface $manager,
        ConfigurationProviderFactory $configurationProviderFactory,
        private readonly CacheManager $cacheManager,
    ) {
        parent::__construct($manager, $configurationProviderFactory);
    }

    public function configure(): void
    {
        $this
            ->setDescription('Compile the assets')
            ->addArgument(
                'destination',
                InputArgument::OPTIONAL,
                'Specify a relative path where the compiled file should be copied to'
            )
            ->addOption(
                'clear-page-cache',
                'x',
                InputOption::VALUE_NONE,
                'Clear the page cache after compilation'
            );
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $destination = (string) $input->getArgument('destination');

        $compileStart = hrtime(true);
        $result = $this->compile();
        $compileEnd = hrtime(true);
        $compileTime = ($compileEnd - $compileStart) / 1_000_000_000;

        if ($result->isErr()) {
            throw $result->unwrapErr();
        }

        $usedPath = $result->unwrap()->getAbsoluteUri();
        if ($destination) {
            $usedPath = $this->copyToDestination($usedPath, $destination);
        }

        $output->writeln(sprintf(
            "Compiled assets and saved file to '%s' in %0.4fs",
            $usedPath,
            $compileTime
        ));

        if ($input->getOption('clear-page-cache')) {
            $this->cacheManager->flushCachesInGroup('pages');
        }

        return self::SUCCESS;
    }
}
