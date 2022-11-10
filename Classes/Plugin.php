<?php
declare(strict_types=1);

namespace Cundd\Assetic;

use Assetic\Exception\FilterException;
use Cundd\Assetic\Configuration\ConfigurationProviderFactory;
use Cundd\Assetic\Configuration\ConfigurationProviderInterface;
use Cundd\Assetic\Exception\MissingConfigurationException;
use Cundd\Assetic\Exception\OutputFileException;
use Cundd\Assetic\Helper\LiveReloadHelper;
use Cundd\Assetic\Utility\Autoloader;
use Cundd\Assetic\Utility\ExceptionPrinter;
use Cundd\Assetic\Utility\GeneralUtility as AsseticGeneralUtility;
use Cundd\Assetic\ValueObject\FilePath;
use Psr\Log\LoggerInterface;
use Throwable;
use TYPO3\CMS\Core\Log\LogManager;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;
use function count;
use function microtime;
use function sprintf;

/**
 * Assetic Plugin
 */
class Plugin
{
    private ManagerInterface $manager;

    private ConfigurationProviderInterface $configurationProvider;

    private LoggerInterface $logger;

    public function __construct(
        ?ManagerInterface $manager = null,
        ?ConfigurationProviderFactory $configurationProviderFactory = null,
        ?LoggerInterface $logger = null
    ) {
        $configurationProviderFactory = $configurationProviderFactory ?? new ConfigurationProviderFactory();
        $this->manager = $manager ?? GeneralUtility::makeInstance(Manager::class);
        $this->configurationProvider = $configurationProviderFactory->build();
        $this->logger = $logger ?? GeneralUtility::makeInstance(LogManager::class)->getLogger(__CLASS__);
    }

    /**
     * Output configured stylesheets as link tags
     *
     * Some processing will be done according to the TypoScript setup of the stylesheets.
     *
     * @return string
     */
    public function main(): string
    {
        AsseticGeneralUtility::profile('Cundd Assetic plugin begin');
        Autoloader::register();

        if (0 === count($this->manager->collectAssets()->all())) {
            throw new MissingConfigurationException('No assets have been defined');
        }

        $collectAndCompileStart = microtime(true);
        $result = $this->manager->collectAndCompile();
        $collectAndCompileEnd = microtime(true);

        if ($result->isErr()) {
            $exception = $result->unwrapErr();

            return $this->handleBuildError($exception) . $this->getLiveReloadCode();
        }

        /** @var FilePath $filePath */
        $filePath = $result->unwrap();
        $content = $this->getLiveReloadCode();
        $content .= $this->addDebugInformation($collectAndCompileEnd, $collectAndCompileStart);
        $content .= sprintf(
            '<link rel="stylesheet" type="text/css" href="%s" media="all">',
            $filePath->getPublicUri()
        );

        AsseticGeneralUtility::profile('Cundd Assetic plugin end');

        return $content;
    }

    private function includeCss(string $renderedStylesheet)
    {
        $renderer = GeneralUtility::makeInstance(PageRenderer::class);
        $renderer->addCssFile(
            $renderedStylesheet,
            'stylesheet',   // rel
            'all',          // media
            '',             // title
            false,          // compress
            true,           // forceOnTop
            '',             // allWrap
            true            // excludeFromConcatenation
        );
    }

    /**
     * Returns the code for "live reload"
     *
     * @return string
     */
    private function getLiveReloadCode(): string
    {
        $helper = new LiveReloadHelper($this->configurationProvider);

        return $helper->getLiveReloadCodeIfEnabled();
    }

    /**
     * Handle exceptions
     *
     * @param FilterException|OutputFileException $exception
     * @return void
     */
    private function handleBuildError(Throwable $exception): string
    {
        /** @var TypoScriptFrontendController $typoScriptFrontendController */
        $typoScriptFrontendController = $GLOBALS['TSFE'];
        $typoScriptFrontendController->set_no_cache();

        $this->logger->error('Caught exception #' . $exception->getCode() . ': ' . $exception->getMessage());

        if ($this->configurationProvider->isDevelopment()) {
            $exceptionPrinter = new ExceptionPrinter();

            return $exceptionPrinter->printException($exception);
        }

        // Always output the exception message if the Assetic classes could not be found (identified by code 1356543545)
        if ($exception->getCode() === 1356543545) {
            return $exception->getMessage();
        }

        return '<!-- Assetic error -->';
    }

    private function addDebugInformation(float $collectAndCompileEnd, float $collectAndCompileStart): string
    {
        $isDevelopmentEnabled = $this->configurationProvider->isDevelopment();
        if (false === $isDevelopmentEnabled || false === AsseticGeneralUtility::isBackendUser()) {
            return '';
        }

        $collectAndCompileTime = $collectAndCompileEnd - $collectAndCompileStart;
        if ($this->manager->willCompile()) {
            return sprintf("<!-- Compiled assets in %0.4fs -->", $collectAndCompileTime);
        } else {
            return sprintf("<!-- Use pre-compiled assets in %0.4fs -->", $collectAndCompileTime);
        }
    }
}
