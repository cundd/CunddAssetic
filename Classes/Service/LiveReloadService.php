<?php

declare(strict_types=1);

namespace Cundd\Assetic\Service;

use Cundd\Assetic\Configuration;
use Cundd\Assetic\ValueObject\CompilationContext;
use Exception;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Security\ContentSecurityPolicy\ConsumableNonce;
use TYPO3\CMS\Core\Security\ContentSecurityPolicy\Directive;
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

    public function loadLiveReloadCodeIfEnabled(
        ServerRequestInterface $request,
        Configuration $configuration,
        CompilationContext $compilationContext,
    ): string {
        if (!$this->isEnabled($configuration, $compilationContext)) {
            return '';
        }

        $port = $configuration->liveReloadConfiguration->port;
        $skipServerTest = $configuration->liveReloadConfiguration->skipServerTest;
        if ($skipServerTest || $this->isServerRunning($configuration, $error)) {
            $resource = $this->getJavaScriptFileUri();
            $code = sprintf(self::JAVASCRIPT_CODE_TEMPLATE, $resource, $port);

            /** @var ConsumableNonce|null $nonceAttribute */
            $nonceAttribute = $request->getAttribute('nonce');
            if ($nonceAttribute instanceof ConsumableNonce) {
                // TODO: Set the correct CSP headers
                //       This is not easy, because the hostname and port must be
                //       known during configuration
                $nonce = $nonceAttribute->consumeInline(Directive::ScriptSrcElem);

                return sprintf('<script nonce="%s">%s</script>', $nonce, $code);
            }

            return sprintf('<script>%s</script>', $code);
        }

        return sprintf(
            '<!-- Could not connect to LiveReload server at port %d: Error %d: %s -->',
            $port,
            $error?->getCode(),
            $error?->getMessage()
        );
    }

    private function isEnabled(
        Configuration $configuration,
        CompilationContext $compilationContext,
    ): bool {
        if (!$configuration->liveReloadConfiguration->isEnabled) {
            return false;
        }

        return $compilationContext->isBackendUserLoggedIn
            || $configuration->allowDeveloperFeaturesWithoutLogin;
    }

    /**
     * Return if the server is running
     */
    private function isServerRunning(
        Configuration $configuration,
        ?Exception &$error = null,
    ): bool {
        $errorNumber = null;
        $connection = @fsockopen(
            'localhost',
            $configuration->liveReloadConfiguration->port,
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
