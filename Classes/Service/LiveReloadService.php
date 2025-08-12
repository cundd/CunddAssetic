<?php

declare(strict_types=1);

namespace Cundd\Assetic\Service;

use Cundd\Assetic\Configuration\ConfigurationProviderFactory;
use Cundd\Assetic\Configuration\ConfigurationProviderInterface;
use Cundd\Assetic\Utility\BackendUserUtility;
use Exception;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Domain\ConsumableString;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\PathUtility as Typo3PathUtility;

use function fclose;

class LiveReloadService implements LiveReloadServiceInterface
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

    private readonly ConfigurationProviderInterface $configurationProvider;

    public function __construct(ConfigurationProviderFactory $configurationProviderFactory)
    {
        $this->configurationProvider = $configurationProviderFactory->build();
    }

    public function loadLiveReloadCodeIfEnabled(ServerRequestInterface $request): string
    {
        if (!$this->isEnabled()) {
            return '';
        }

        $port = $this->configurationProvider->getLiveReloadConfiguration()->getPort();
        if ($this->skipServerTest() || $this->isServerRunning($error)) {
            $resource = $this->getJavaScriptFileUri();
            $code = sprintf(self::JAVASCRIPT_CODE_TEMPLATE, $resource, $port);

            /** @var ConsumableString|null $nonceAttribute */
            $nonceAttribute = $request->getAttribute('nonce');
            if ($nonceAttribute instanceof ConsumableString) {
                // TODO: Set the correct CSP headers
                //       This is not easy, because the hostname and port must be known during configuration
                $nonce = $nonceAttribute->consume();

                return sprintf('<script nonce="%s">%s</script>', $nonce, $code);
            }

            return sprintf('<script>%s</script>', $code);
        }

        /* @var Exception $error */

        return sprintf(
            '<!-- Could not connect to LiveReload server at port %d: Error %d: %s -->',
            $port,
            $error?->getCode(),
            $error?->getMessage()
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
        $errorNumber = null;
        $connection = @fsockopen(
            'localhost',
            $this->getPort(),
            $errorNumber,
            $errorString,
            0.1
        );

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
