<?php
declare(strict_types=1);

namespace Cundd\Assetic;

use Cundd\Assetic\Configuration\ConfigurationProvider;
use Cundd\Assetic\Helper\LiveReloadHelper;
use Cundd\Assetic\Utility\GeneralUtility as AsseticGeneralUtility;
use Cundd\CunddComposer\Autoloader;
use LogicException;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\ContentObject\AbstractContentObject;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use function microtime;
use function sprintf;

/**
 * Assetic Plugin
 */
class Plugin
{
    /**
     * Content object
     *
     * @var AbstractContentObject|ContentObjectRenderer
     */
    public $cObj;

    /**
     * Asset manager
     *
     * @var ManagerInterface
     */
    protected $manager;

    /**
     * @var array
     * @deprecated
     */
    protected $configuration;

    /**
     * @var ConfigurationProvider
     */
    private $configurationProvider;

    /**
     * Output configured stylesheets as link tags
     *
     * Some processing will be done according to the TypoScript setup of the stylesheets.
     *
     * @param string $content
     * @param array  $conf
     * @return string
     */
    public function main($content, $conf)
    {
        AsseticGeneralUtility::profile('Cundd Assetic plugin begin');
        Autoloader::register();

        $this->configuration = $conf;
        $this->configurationProvider = new ConfigurationProvider($conf);
        $this->manager = new Manager($this->configurationProvider);

        // `force_on_top` only works if caching is enabled
        // $forceOnTop = (bool)($conf['force_on_top'] ?? false);
        $forceOnTop = false;

        try {
            $collectAndCompileStart = microtime(true);
            $renderedStylesheet = $this->manager->collectAndCompile();
            $collectAndCompileEnd = microtime(true);

            $content = $this->getLiveReloadCode();
            $content .= $this->addDebugInformation($collectAndCompileEnd, $collectAndCompileStart);
            if ($forceOnTop) {
                $this->includeCss($renderedStylesheet);
            } else {
                $content .= '<link rel="stylesheet" type="text/css" href="' . $renderedStylesheet . '" media="all">';
            }
        } catch (LogicException $exception) {
            if ($exception->getCode() === 1356543545) {
                return $exception->getMessage();
            }
        }
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
    private function getLiveReloadCode()
    {
        $helper = new LiveReloadHelper($this->configurationProvider);

        return $helper->getLiveReloadCodeIfEnabled();
    }

    /**
     * @param        $collectAndCompileEnd
     * @param        $collectAndCompileStart
     * @return string
     */
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
