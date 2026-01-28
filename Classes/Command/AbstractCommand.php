<?php

declare(strict_types=1);

namespace Cundd\Assetic\Command;

use Cundd\Assetic\Configuration;
use Cundd\Assetic\Configuration\ConfigurationFactory;
use Cundd\Assetic\Exception\MissingConfigurationException;
use Cundd\Assetic\ManagerInterface;
use Cundd\Assetic\Utility\PathUtility;
use Cundd\Assetic\ValueObject\CompilationContext;
use Cundd\Assetic\ValueObject\FilePath;
use Cundd\Assetic\ValueObject\Result;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Throwable;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\SiteFinder;

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
    protected const ARGUMENT_SITE = 'site';

    public function __construct(
        private readonly ManagerInterface $manager,
        private readonly ConfigurationFactory $configurationFactory,
        private readonly SiteFinder $siteFinder,
    ) {
        parent::__construct();
    }

    protected function registerDefaultArgumentsAndOptions(): static
    {
        return $this
            ->addArgument(
                self::ARGUMENT_SITE,
                InputArgument::REQUIRED,
                'Site for which to load the configuration'
            );
    }

    /**
     * Compile the assets
     *
     * @return Result<FilePath,covariant Throwable>
     */
    protected function compile(
        Configuration $configuration,
        CompilationContext $compilationContext,
    ): Result {
        $this->manager->forceCompile();

        if (0 === count($this->manager->collectAssets($configuration)->all())) {
            throw new MissingConfigurationException(
                'No assets have been found',
                1886548090
            );
        }

        return $this->manager->forceCompile()->collectAndCompile(
            $configuration,
            $compilationContext
        );
    }

    /**
     * Copy the source to the destination
     *
     * @return string Returns the used path
     */
    protected function copyToDestination(
        Configuration $configuration,
        string $source,
        string $destination,
    ): string {
        if (!$destination) {
            return $source;
        }

        // Check if the filename has to be appended
        if ('/' === substr($destination, -1)) {
            $destination .= basename($source);
        } elseif (intval(strrpos($destination, '.')) < intval(strrpos($destination, '/'))) {
            $destination .= '/' . basename($source);
        }

        $destination = PathUtility::getAbsolutePath($destination);
        if (!file_exists(dirname($destination))) {
            mkdir(dirname($destination), 0775, true);
        }
        if (copy($source, $destination)) {
            return $destination;
        }

        return $source;
    }

    protected function getCompilationContext(
        InputInterface $input,
    ): CompilationContext {
        $siteIdentifier = $input->getArgument(self::ARGUMENT_SITE);
        try {
            $site = $this->siteFinder->getSiteByIdentifier($siteIdentifier);
        } catch (SiteNotFoundException $e) {
            $options = implode(
                ', ',
                array_map(
                    fn (Site $site) => $site->getIdentifier(),
                    $this->siteFinder->getAllSites()
                )
            );
            throw new RuntimeException(
                $e->getMessage() . '. Valid options are: ' . $options,
                1769528743,
                $e
            );
        }

        return new CompilationContext(
            site: $site,
            isBackendUserLoggedIn: false,
            isCliEnvironment: true,
        );
    }

    final protected function getConfiguration(
        CompilationContext $compilationContext,
    ): Configuration {
        return $this->configurationFactory->buildFromCli($compilationContext);
    }
}
