<?php

namespace Cundd\Assetic\Helper;

use Cundd\Assetic\ManagerInterface;
use Cundd\Assetic\Utility\GeneralUtility as AsseticGeneralUtility;
use Exception;
use TYPO3\CMS\Core\Core\Environment;
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
        if ($this->isServerRunning($error)) {
            $resource = 'EXT:assetic/Resources/Public/Library/livereload.js';
            $resource = '/' . str_replace(
                    Environment::getPublicPath() . '/',
                    '',
                    GeneralUtility::getFileAbsFileName($resource)
                );
            $code = sprintf(self::JAVASCRIPT_CODE_TEMPLATE, $resource, $port);

            return "<script>$code</script>";
        }

        /** @var Exception $error */

        return sprintf(
            '<!-- Could not connect to LiveReload server at port %d: Error %d: %s -->',
            $port,
            $error->getCode(),
            $error->getMessage()
        );
    }

    /**
     * Returns the Live Reload server port
     *
     * @return int
     */
    private function getPort()
    {
        if (isset($this->configuration['livereload.']) && isset($this->configuration['livereload.']['port'])) {
            return intval($this->configuration['livereload.']['port']);
        }

        return 35729;
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
     * @param Exception $error
     * @return bool
     */
    private function isServerRunning(&$error = null)
    {
        $connection = @fsockopen('localhost', $this->getPort(), $errorNumber, $errorString, 1.0);

        if (is_resource($connection)) {
            return true;
        }

        $error = new Exception($errorString, $errorNumber);

        return false;
    }
}
