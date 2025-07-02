<?php

declare(strict_types=1);

namespace Cundd\Assetic\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use function microtime;
use function sprintf;

/**
 * Command to compile assets
 */
class CompileCommand extends AbstractCommand
{
    public function configure(): void
    {
        $this
            ->setDescription('Compile the assets')
            ->addArgument(
                'destination',
                InputArgument::OPTIONAL,
                'Specify a relative path where the compiled file should be copied to'
            );
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        // @phpstan-ignore cast.string
        $destination = (string) $input->getArgument('destination');

        $compileStart = microtime(true);
        $result = $this->compile();
        if ($result->isErr()) {
            throw $result->unwrapErr();
        }

        $usedPath = $result->unwrap()->getAbsoluteUri();
        $compileEnd = microtime(true);
        if ($destination) {
            $usedPath = $this->copyToDestination($usedPath, $destination);
        }
        $compileTime = $compileEnd - $compileStart;

        $output->writeln(sprintf("Compiled assets and saved file to '%s' in %0.4fs", $usedPath, $compileTime));

        return 0;
    }
}
