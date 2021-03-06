<?php
declare(strict_types=1);

namespace Cundd\Assetic\Helper;

use Cundd\Assetic\Configuration\ConfigurationProvider;
use Cundd\Assetic\Utility\ConfigurationUtility;
use Cundd\Assetic\Utility\GeneralUtility as AsseticGeneralUtility;
use Exception;
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
     * @var ConfigurationProvider
     */
    private $configurationProvider;

    public function __construct(ConfigurationProvider $configurationProvider)
    {
        $this->configurationProvider = $configurationProvider;
    }

    /**
     * @return string
     */
    public function getLiveReloadCodeIfEnabled()
    {
        if (!$this->isEnabled()) {
            return '';
        }

        $port = $this->configurationProvider->getLiveReloadConfiguration()->getPort();
        if ($this->skipServerTest() || $this->isServerRunning($error)) {
            $resource = 'EXT:assetic/Resources/Public/Library/livereload.js';
            $resource = '/' . str_replace(
                    $this->configurationProvider->getPublicPath(),
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
     * Return the Live Reload server port
     *
     * @return int
     */
    private function getPort()
    {
        return $this->configurationProvider->getLiveReloadConfiguration()->getPort();
    }

    /**
     * Return if the livereload code should be inserted even if the server connection is not available
     *
     * @return int
     */
    private function skipServerTest()
    {
        return $this->configurationProvider->getLiveReloadConfiguration()->getSkipServerTest();
    }

    /**
     * Return if Live Reload is enabled
     *
     * @return bool
     */
    private function isEnabled()
    {
        return $this->configurationProvider->getEnableExperimentalFeatures() && AsseticGeneralUtility::isBackendUser();
    }

    /**
     * Return if the server is running
     *
     * @param Exception|null $error
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
