<?php
declare(strict_types=1);

namespace Cundd\Assetic\Command;

use Cundd\CunddComposer\Autoloader;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Command to compile assets
 */
class CompileCommand extends AbstractCommand implements ColorInterface
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

    public function execute(InputInterface $input, OutputInterface $output)
    {
        Autoloader::register();
        $destination = $input->getArgument('destination');

        $usedPath = $sourcePath = $this->compile(false);
        if ($destination) {
            $usedPath = $this->copyToDestination($sourcePath, $destination);
        }

        $output->writeln("Compiled assets and saved file to '$usedPath'");
    }
}
