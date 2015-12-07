<?php
namespace Cundd\Assetic;

/*
 * Copyright (C) 2012 Daniel Corn
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 */

use Cundd\Assetic\Utility\GeneralUtility as AsseticGeneralUtility;
use Cundd\CunddComposer\Autoloader;
use TYPO3\CMS\Core\Utility\GeneralUtility;
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
            $content .= '<link rel="stylesheet" type="text/css" href="'.$renderedStylesheet.'" media="all">';
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
        if (!$this->manager->getExperimental() || !AsseticGeneralUtility::isBackendUser()) {
            return '';
        }

        $port = 35729;
        if (isset($this->configuration['livereload.']) && isset($this->configuration['livereload.']['port'])) {
            $port = intval($this->configuration['livereload.']['port']);
        }

        $resource = 'EXT:assetic/Resources/Public/Library/livereload.js';
        $resource = '/'.str_replace(PATH_site, '', GeneralUtility::getFileAbsFileName($resource));
        $javaScriptCodeTemplate = "<script type=\"text/javascript\">
	(function () {
	    document.addEventListener('DOMContentLoaded', function() {
            var scriptElement = document.createElement('script');
            scriptElement.src = '%s' + '?host=' + location.host + '&port=%d';
            document.getElementsByTagName('head')[0].appendChild(scriptElement);
	    });
	})();
</script>";

        return sprintf($javaScriptCodeTemplate, $resource, $port);
    }
}
