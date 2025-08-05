<?php

declare(strict_types=1);

namespace Cundd\Assetic\Command;

use Cundd\Assetic\Configuration\ConfigurationProviderFactory;
use Cundd\Assetic\Configuration\ConfigurationProviderInterface;
use Cundd\Assetic\Exception\MissingConfigurationException;
use Cundd\Assetic\ManagerInterface;
use Cundd\Assetic\ValueObject\FilePath;
use Cundd\Assetic\ValueObject\Result;
use Symfony\Component\Console\Command\Command;
use Throwable;

use function basename;
use function copy;
use function count;
use function dirname;
use function file_exists;
use function intval;
use function mkdir;
use function strrpos;
use function substr;

abstract class AbstractCommand extends Command
{
    private ManagerInterface $manager;

    private ConfigurationProviderInterface $configurationProvider;

    public function __construct(
        ManagerInterface $manager,
        ConfigurationProviderFactory $configurationProviderFactory,
    ) {
        parent::__construct();
        $this->manager = $manager;
        $this->configurationProvider = $configurationProviderFactory->build();
    }

    /**
     * Compile the assets
     *
     * @return Result<FilePath,covariant Throwable>
     */
    protected function compile(): Result
    {
        $this->manager->forceCompile();

        if (0 === count($this->manager->collectAssets()->all())) {
            throw new MissingConfigurationException('No assets have been found', 1886548090);
        }
        $outputFileLinkResult = $this->manager->forceCompile()->collectAndCompile();
        $this->manager->clearHashCache();

        return $outputFileLinkResult;
    }

    /**
     * Copy the source to the destination
     *
     * @return string Returns the used path
     */
    protected function copyToDestination(string $source, string $destination): string
    {
        if (!$destination) {
            return $source;
        }

        // Check if the filename has to be appended
        if ('/' === substr($destination, -1)) {
            $destination .= basename($source);
        } elseif (intval(strrpos($destination, '.')) < intval(strrpos($destination, '/'))) {
            $destination .= '/' . basename($source);
        }

        $destination = $this->getConfigurationProvider()->getPublicPath() . '/' . $destination;
        if (!file_exists(dirname($destination))) {
            mkdir(dirname($destination), 0775, true);
        }
        if (copy($source, $destination)) {
            return $destination;
        }

        return $source;
    }

    protected function getConfigurationProvider(): ConfigurationProviderInterface
    {
        return $this->configurationProvider;
    }
}
