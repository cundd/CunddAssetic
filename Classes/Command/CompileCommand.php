<?php

declare(strict_types=1);

namespace Cundd\Assetic\Command;

use Cundd\Assetic\Configuration;
use Cundd\Assetic\Configuration\ConfigurationFactory;
use Cundd\Assetic\ManagerInterface;
use Cundd\Assetic\ValueObject\FilePath;
use Cundd\Assetic\ValueObject\Result;
use Cundd\Assetic\ValueObject\Result\Err;
use Cundd\Assetic\ValueObject\Result\Ok;
use RuntimeException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Site\SiteFinder;

use function sprintf;

/**
 * Command to compile assets
 */
class CompileCommand extends AbstractCommand
{
    public function __construct(
        ManagerInterface $manager,
        ConfigurationFactory $configurationFactory,
        SiteFinder $siteFinder,
        private readonly CacheManager $cacheManager,
    ) {
        parent::__construct(
            $manager,
            $configurationFactory,
            $siteFinder
        );
    }

    public function configure(): void
    {
        $this->setDescription('Compile the assets');
        $this->registerDefaultArgumentsAndOptions();
        $this
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
        $compilationContext = $this->getCompilationContext($input);
        $configuration = $this->getConfiguration($compilationContext);
        $destination = (string) $input->getArgument('destination');

        $compileStart = hrtime(true);
        $result = $this->compile($configuration, $compilationContext);
        $compileEnd = hrtime(true);
        $compileTime = ($compileEnd - $compileStart) / 1_000_000_000;

        if ($result->isErr()) {
            throw $result->unwrapErr();
        }

        $path = $result->unwrap();
        $result = $this->handleDestination(
            $output,
            $configuration,
            $path,
            $destination,
            $compileTime
        );

        if ($input->getOption('clear-page-cache')) {
            $this->cacheManager->flushCachesInGroup('pages');
        }

        return $result->isOk() ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @return Result<null,RuntimeException>
     */
    private function handleDestination(
        OutputInterface $output,
        Configuration $configuration,
        FilePath $file,
        string $destination,
        float $compileTime,
    ): Result {
        if ('-' === $destination) {
            return $this->writeToStdOut($output, $file, $compileTime);
        }

        if ($destination) {
            $finalPath = $this->copyToDestination(
                $configuration,
                $file->getAbsoluteUri(),
                $destination
            );
        } else {
            $finalPath = $file->getAbsoluteUri();
        }

        $output->writeln(sprintf(
            "Compiled assets and saved file to '%s' in %0.4fs",
            $finalPath,
            $compileTime
        ));

        return new Ok(null);
    }

    /**
     * @return Result<null,RuntimeException>
     */
    private function writeToStdOut(
        OutputInterface $output,
        FilePath $filePath,
        float $compileTime,
    ): Result {
        $errorOutput = $output instanceof ConsoleOutputInterface
            ? $output->getErrorOutput()
            : null;

        $absolutePath = $filePath->getAbsoluteUri();
        $content = file_get_contents($absolutePath);
        if (false === $content) {
            $message = sprintf(
                'Could not read the compiled file at "%s"',
                $absolutePath
            );
            $errorOutput?->writeln($message);

            return new Err(new RuntimeException($message));
        }

        $output->write(
            $content,
            options: OutputInterface::OUTPUT_RAW | OutputInterface::VERBOSITY_QUIET
        );
        $errorOutput?->writeln(sprintf(
            'Compiled assets in %0.4fs',
            $compileTime
        ));

        return new Ok(null);
    }
}
