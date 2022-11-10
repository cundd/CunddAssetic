<?php
declare(strict_types=1);

namespace Cundd\Assetic\Command;

use Cundd\Assetic\Utility\Autoloader;
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
    public function configure()
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
        Autoloader::register();
        $destination = $input->getArgument('destination');

        $compileStart = microtime(true);
        $usedPath = (string)$this->compile(false);
        $compileEnd = microtime(true);
        if ($destination) {
            $usedPath = $this->copyToDestination($usedPath, $destination);
        }
        $compileTime = $compileEnd - $compileStart;

        $output->writeln(sprintf("Compiled assets and saved file to '%s' in %0.4fs", $usedPath, $compileTime));

        return 0;
    }
}
