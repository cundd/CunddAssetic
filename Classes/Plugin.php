<?php
declare(strict_types=1);

namespace Cundd\Assetic;

use Cundd\Assetic\Helper\LiveReloadHelper;
use Cundd\Assetic\Utility\GeneralUtility as AsseticGeneralUtility;
use Cundd\CunddComposer\Autoloader;
use LogicException;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\ContentObject\AbstractContentObject;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;

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
     */
    protected $configuration;

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
        $this->manager = new Manager($conf);

        // `force_on_top` only works if caching is enabled
        // $forceOnTop = (bool)($conf['force_on_top'] ?? false);
        $forceOnTop = false;

        try {
            $renderedStylesheet = $this->manager->collectAndCompile();

            $content = $this->getLiveReloadCode();
            if (!$forceOnTop) {
                $content .= '<link rel="stylesheet" type="text/css" href="' . $renderedStylesheet . '" media="all">';
            } else {
                $this->includeCss($renderedStylesheet);
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
        $helper = new LiveReloadHelper($this->manager, $this->configuration);

        return $helper->getLiveReloadCodeIfEnabled();
    }
}
