<?php

declare(strict_types=1);

namespace Cundd\Assetic\Helper;

use Cundd\Assetic\Configuration\ConfigurationProviderInterface;
use Cundd\Assetic\Utility\BackendUserUtility;
use Exception;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\PathUtility as Typo3PathUtility;

use function fclose;

/**
 * Helper class to generate the Live Reload code
 */
class LiveReloadHelper
{
    private const JAVASCRIPT_CODE_TEMPLATE = /* @lang JavaScript */
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

    public function __construct(private readonly ConfigurationProviderInterface $configurationProvider)
    {
    }

    public function getLiveReloadCodeIfEnabled(): string
    {
        if (!$this->isEnabled()) {
            return '';
        }

        $port = $this->configurationProvider->getLiveReloadConfiguration()->getPort();
        if ($this->skipServerTest() || $this->isServerRunning($error)) {
            $resource = $this->getJavaScriptFileUri();
            $code = sprintf(self::JAVASCRIPT_CODE_TEMPLATE, $resource, $port);

            return "<script>$code</script>";
        }

        /* @var Exception $error */

        return sprintf(
            '<!-- Could not connect to LiveReload server at port %d: Error %d: %s -->',
            $port,
            $error->getCode(),
            $error->getMessage()
        );
    }

    /**
     * Return the Live Reload server port
     */
    private function getPort(): int
    {
        return $this->configurationProvider->getLiveReloadConfiguration()->getPort();
    }

    /**
     * Return if the livereload code should be inserted even if the server connection is not available
     */
    private function skipServerTest(): bool
    {
        return $this->configurationProvider->getLiveReloadConfiguration()->getSkipServerTest();
    }

    /**
     * Return if Live Reload is enabled
     */
    private function isEnabled(): bool
    {
        if (!$this->configurationProvider->getLiveReloadConfiguration()->isEnabled()) {
            return false;
        }

        return BackendUserUtility::isUserLoggedIn()
            || $this->configurationProvider->getAllowCompileWithoutLogin();
    }

    /**
     * Return if the server is running
     */
    private function isServerRunning(?Exception &$error = null): bool
    {
        $connection = @fsockopen('localhost', $this->getPort(), $errorNumber, $errorString, 0.1);

        if (is_resource($connection)) {
            fclose($connection);

            return true;
        }

        $error = new Exception($errorString, $errorNumber);

        return false;
    }

    private function getJavaScriptFileUri(): string
    {
        $file = Typo3PathUtility::getAbsoluteWebPath(
            GeneralUtility::getFileAbsFileName(
                'EXT:assetic/Resources/Public/Library/livereload.js'
            )
        );

        return GeneralUtility::createVersionNumberedFilename($file);
    }
}
