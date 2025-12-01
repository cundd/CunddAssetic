<?php

declare(strict_types=1);

namespace Cundd\Assetic;

use Assetic\Exception\FilterException;
use Cundd\Assetic\Configuration\ConfigurationFactory;
use Cundd\Assetic\Exception\MissingConfigurationException;
use Cundd\Assetic\Exception\OutputFileException;
use Cundd\Assetic\Service\LiveReloadServiceInterface;
use Cundd\Assetic\Utility\ExceptionPrinter;
use Cundd\Assetic\Utility\ProfilingUtility;
use Cundd\Assetic\ValueObject\CompilationContext;
use Cundd\Assetic\ValueObject\FilePath;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Throwable;
use TYPO3\CMS\Core\Context\Context;
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
    private readonly ManagerInterface $manager;

    private readonly ConfigurationFactory $configurationFactory;

    private readonly LoggerInterface $logger;

    private readonly Context $context;

    private readonly LiveReloadServiceInterface $liveReloadService;

    public function __construct()
    {
        $this->manager = GeneralUtility::makeInstance(Manager::class);
        $this->context = GeneralUtility::makeInstance(Context::class);
        $this->liveReloadService = GeneralUtility::makeInstance(
            LiveReloadServiceInterface::class
        );
        $this->configurationFactory = GeneralUtility::makeInstance(
            ConfigurationFactory::class
        );
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
    public function main(
        string $content,
        array $conf,
        ServerRequestInterface $request,
    ): string {
        ProfilingUtility::profile('Cundd Assetic plugin begin');

        $compilationContext = new CompilationContext(
            site: $request->getAttribute('site'),
            isBackendUserLoggedIn: $this->context->getPropertyFromAspect(
                'backend.user',
                'isLoggedIn'
            ),
            isCliEnvironment: false
        );
        $configuration = $this->configurationFactory
            ->buildFromRequest($request, $compilationContext);

        if (0 === count($this->manager->collectAssets($configuration)->all())) {
            throw new MissingConfigurationException('No assets have been defined', 4491033249);
        }

        $collectAndCompileStart = hrtime(true);
        $result = $this->manager->collectAndCompile(
            $configuration,
            $compilationContext
        );
        $collectAndCompileEnd = hrtime(true);

        if ($result->isErr()) {
            $exception = $result->unwrapErr();
            $liveReloadCode = $this->getLiveReloadCode(
                $request,
                $configuration,
                $compilationContext
            );

            return $this->handleBuildError(
                $configuration,
                $request,
                $exception
            ) . $liveReloadCode;
        }

        /** @var FilePath $filePath */
        $filePath = $result->unwrap();
        $publicUri = $filePath->getPublicUri() . ($filePath->isSymlink() ? '?' . time() : '');
        $content = $this->getLiveReloadCode(
            $request,
            $configuration,
            $compilationContext
        );
        $content .= $this->addDebugInformation(
            $configuration,
            $compilationContext,
            $collectAndCompileEnd,
            $collectAndCompileStart
        );
        $content .= sprintf(
            '<link rel="stylesheet" type="text/css" href="%s" media="all">',
            $publicUri
        );

        ProfilingUtility::profile('Cundd Assetic plugin end');

        return $content;
    }

    /**
     * Return the code for "live reload"
     */
    private function getLiveReloadCode(
        ServerRequestInterface $request,
        Configuration $configuration,
        CompilationContext $compilationContext,
    ): string {
        return $this->liveReloadService->loadLiveReloadCodeIfEnabled(
            $request,
            $configuration,
            $compilationContext
        );
    }

    /**
     * Handle exceptions
     *
     * @param FilterException|OutputFileException|Throwable $exception
     */
    private function handleBuildError(
        Configuration $configuration,
        ServerRequestInterface $request,
        Throwable $exception,
    ): string {
        $this->disableCache($request);

        $this->logger->error(
            'Caught exception #' . $exception->getCode() . ': ' . $exception->getMessage(),
            ['exception' => $exception]
        );

        if ($configuration->isDevelopment) {
            $exceptionPrinter = new ExceptionPrinter();

            return $exceptionPrinter->printException($exception);
        }

        // Always output the exception message if the Assetic classes could not
        // be found (identified by code 1356543545)
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
            $typoScriptFrontendController->set_no_cache('Assetic error');
        }
    }

    private function addDebugInformation(
        Configuration $configuration,
        CompilationContext $compilationContext,
        float $collectAndCompileEnd,
        float $collectAndCompileStart,
    ): string {
        if (false === $configuration->isDevelopment || false === $compilationContext->isBackendUserLoggedIn) {
            return '';
        }

        $duration = sprintf(
            '%.6fs',
            ($collectAndCompileEnd - $collectAndCompileStart) / 1_000 / 1_000 / 1_000
        );
        if ($this->manager->willCompile($configuration, $compilationContext)) {
            return sprintf('<!-- Compiled assets in %s -->', $duration);
        } else {
            return sprintf('<!-- Use pre-compiled assets in %s -->', $duration);
        }
    }
}
