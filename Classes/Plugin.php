<?php

declare(strict_types=1);

namespace Cundd\Assetic;

use Assetic\Exception\FilterException;
use Cundd\Assetic\Configuration\ConfigurationProviderFactory;
use Cundd\Assetic\Configuration\ConfigurationProviderInterface;
use Cundd\Assetic\Exception\MissingConfigurationException;
use Cundd\Assetic\Exception\OutputFileException;
use Cundd\Assetic\Service\LiveReloadServiceInterface;
use Cundd\Assetic\Utility\BackendUserUtility;
use Cundd\Assetic\Utility\ExceptionPrinter;
use Cundd\Assetic\Utility\ProfilingUtility;
use Cundd\Assetic\ValueObject\FilePath;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Throwable;
use TYPO3\CMS\Core\Log\LogManager;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;

use function count;
use function hrtime;
use function sprintf;

/**
 * Assetic Plugin
 */
class Plugin
{
    private ManagerInterface $manager;

    private ConfigurationProviderInterface $configurationProvider;

    private LoggerInterface $logger;

    public function __construct()
    {
        $this->manager = GeneralUtility::makeInstance(Manager::class);
        $this->configurationProvider = (new ConfigurationProviderFactory())
            ->build();
        $this->logger = GeneralUtility::makeInstance(LogManager::class)
            ->getLogger(__CLASS__);
    }

    /**
     * Output configured stylesheets as link tags
     *
     * Some processing will be done according to the TypoScript setup of the stylesheets.
     *
     * @param array<string,mixed> $conf
     */
    public function main(string $content, array $conf, ServerRequestInterface $request): string
    {
        ProfilingUtility::profile('Cundd Assetic plugin begin');

        if (0 === count($this->manager->collectAssets()->all())) {
            throw new MissingConfigurationException('No assets have been defined', 4491033249);
        }

        $collectAndCompileStart = hrtime(true);
        $result = $this->manager->collectAndCompile();
        $collectAndCompileEnd = hrtime(true);

        if ($result->isErr()) {
            $exception = $result->unwrapErr();

            return $this->handleBuildError($request, $exception) . $this->getLiveReloadCode($request);
        }

        /** @var FilePath $filePath */
        $filePath = $result->unwrap();
        $publicUri = $filePath->getPublicUri() . ($filePath->isSymlink() ? '?' . time() : '');
        $content = $this->getLiveReloadCode($request);
        $content .= $this->addDebugInformation($collectAndCompileEnd, $collectAndCompileStart);
        $content .= sprintf(
            '<link rel="stylesheet" type="text/css" href="%s" media="all">',
            $publicUri
        );

        ProfilingUtility::profile('Cundd Assetic plugin end');

        return $content;
    }

    /**
     * Returns the code for "live reload"
     */
    private function getLiveReloadCode(ServerRequestInterface $request): string
    {
        $liveReloadService = GeneralUtility::makeInstance(LiveReloadServiceInterface::class);

        return $liveReloadService->loadLiveReloadCodeIfEnabled($request);
    }

    /**
     * Handle exceptions
     *
     * @param FilterException|OutputFileException|Throwable $exception
     */
    private function handleBuildError(ServerRequestInterface $request, Throwable $exception): string
    {
        $this->disableCache($request);

        $this->logger->error('Caught exception #' . $exception->getCode() . ': ' . $exception->getMessage());

        if ($this->configurationProvider->isDevelopment()) {
            $exceptionPrinter = new ExceptionPrinter();

            return $exceptionPrinter->printException($exception);
        }

        // Always output the exception message if the Assetic classes could not be found (identified by code 1356543545)
        if (1356543545 === $exception->getCode()) {
            return $exception->getMessage();
        }

        return '<!-- Assetic error -->';
    }

    private function disableCache(ServerRequestInterface $request): void
    {
        /** @var \TYPO3\CMS\Frontend\Cache\CacheInstruction|null $frontendCacheInstruction */
        $frontendCacheInstruction = $request->getAttribute('frontend.cache.instruction');
        if ($frontendCacheInstruction) {
            $frontendCacheInstruction->disableCache('Assetic error');
        } else {
            /** @var TypoScriptFrontendController $typoScriptFrontendController */
            $typoScriptFrontendController = $GLOBALS['TSFE'];
            $typoScriptFrontendController->set_no_cache();
        }
    }

    private function addDebugInformation(float $collectAndCompileEnd, float $collectAndCompileStart): string
    {
        $isDevelopmentEnabled = $this->configurationProvider->isDevelopment();
        if (false === $isDevelopmentEnabled || false === BackendUserUtility::isUserLoggedIn()) {
            return '';
        }

        $duration = sprintf(
            '%.6fs',
            ($collectAndCompileEnd - $collectAndCompileStart) / 1_000 / 1_000 / 1_000
        );
        if ($this->manager->willCompile()) {
            return sprintf('<!-- Compiled assets in %s -->', $duration);
        } else {
            return sprintf('<!-- Use pre-compiled assets in %s -->', $duration);
        }
    }
}
