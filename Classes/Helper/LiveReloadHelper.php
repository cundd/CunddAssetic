<?php
/*
 *  Copyright notice
 *
 *  (c) 2016 Andreas Thurnheer-Meier <tma@iresults.li>, iresults
 *  Daniel Corn <cod@iresults.li>, iresults
 *
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 */

/**
 * @author COD
 * Created 13.10.16 10:01
 */


namespace Cundd\Assetic\Helper;

use Cundd\Assetic\ManagerInterface;
use Cundd\Assetic\Utility\GeneralUtility as AsseticGeneralUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Helper class to generate the Live Reload code
 */
class LiveReloadHelper
{
    const JAVASCRIPT_CODE_TEMPLATE = /** @lang JavaScript */
        <<<JAVASCRIPT_CODE_TEMPLATE
(function () {
    document.addEventListener('DOMContentLoaded', function () {
        var scriptElement = document.createElement('script');
        scriptElement.async = true;
        scriptElement.src = '%s' + '?host=' + location.host + '&port=%d';
        document.getElementsByTagName('head')[0].appendChild(scriptElement);
    });
})();
JAVASCRIPT_CODE_TEMPLATE;


    /**
     * Asset manager
     *
     * @var ManagerInterface
     */
    private $manager;

    /**
     * @var array
     */
    private $configuration;

    /**
     * LiveReloadHelper constructor
     *
     * @param ManagerInterface $manager
     * @param array            $configuration
     */
    public function __construct(ManagerInterface $manager, array $configuration)
    {
        $this->manager = $manager;
        $this->configuration = $configuration;
    }

    /**
     * @return string
     */
    public function getLiveReloadCodeIfEnabled()
    {
        if (!$this->isEnabled()) {
            return '';
        }

        $port = $this->getPort();
        if ($this->isServerRunning()) {
            $resource = 'EXT:assetic/Resources/Public/Library/livereload.js';
            $resource = '/' . str_replace(PATH_site, '', GeneralUtility::getFileAbsFileName($resource));
            $code = sprintf(self::JAVASCRIPT_CODE_TEMPLATE, $resource, $port);

            return "<script>$code</script>";
        }

        return sprintf('<!-- Could not connect to LiveReload server at port %d -->', $port);
    }

    /**
     * Returns the Live Reload server port
     *
     * @return int
     */
    private function getPort()
    {
        $port = 35729;
        if (isset($this->configuration['livereload.']) && isset($this->configuration['livereload.']['port'])) {
            $port = intval($this->configuration['livereload.']['port']);

            return $port;
        }

        return $port;
    }

    /**
     * Returns if Live Reload is enabled
     *
     * @return bool
     */
    private function isEnabled()
    {
        return $this->manager->getExperimental() && AsseticGeneralUtility::isBackendUser();
    }

    /**
     * Returns if the server is running
     *
     * @return bool
     */
    private function isServerRunning()
    {
        $connection = @fsockopen('localhost', $this->getPort());

        return is_resource($connection);
    }

}
