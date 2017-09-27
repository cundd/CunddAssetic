<?php

namespace Cundd\Assetic;


use Cundd\Assetic\Helper\LiveReloadHelper;
use Cundd\Assetic\Utility\GeneralUtility as AsseticGeneralUtility;
use Cundd\CunddComposer\Autoloader;
use TYPO3\CMS\Frontend\ContentObject\AbstractContentObject;

/**
 * Assetic Plugin
 *
 * @package Cundd_Assetic
 */
class Plugin
{
    /**
     * Content object
     *
     * @var AbstractContentObject
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
     * @author Daniel Corn <info@cundd.net>
     */
    public function main($content, $conf)
    {
        AsseticGeneralUtility::profile('Cundd Assetic plugin begin');
        Autoloader::register();

        $this->configuration = $conf;
        $this->manager = new Manager($conf);

        try {
            $renderedStylesheet = $this->manager->collectAndCompile();

            $content = '';
            $content .= '<link rel="stylesheet" type="text/css" href="' . $renderedStylesheet . '" media="all">';
            $content .= $this->getLiveReloadCode();
        } catch (\LogicException $exception) {
            if ($exception->getCode() === 1356543545) {
                return $exception->getMessage();
            }
        }
        AsseticGeneralUtility::profile('Cundd Assetic plugin end');

        return $content;
    }

    /**
     * Returns the code for "live reload"
     *
     * @return string
     */
    protected function getLiveReloadCode()
    {
        $helper = new LiveReloadHelper($this->manager, $this->configuration);

        return $helper->getLiveReloadCodeIfEnabled();
    }
}
