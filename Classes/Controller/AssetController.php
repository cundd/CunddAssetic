<?php

declare(strict_types=1);

namespace Cundd\Assetic\Controller;

use Cundd\Assetic\Compiler\AssetCollector;
use Cundd\Assetic\Configuration\ConfigurationFactory;
use Cundd\Assetic\ManagerInterface;
use Cundd\Assetic\Service\SessionServiceInterface;
use Cundd\Assetic\ValueObject\CompilationContext;
use Cundd\Assetic\ValueObject\FilePath;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Backend\Attribute\AsController;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Site\Entity\NullSite;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;

#[AsController]
class AssetController extends ActionController
{
    public function __construct(
        private readonly ManagerInterface $manager,
        private readonly CacheManager $cacheManager,
        private readonly SessionServiceInterface $sessionService,
        private readonly ModuleTemplateFactory $moduleTemplateFactory,
        private readonly ConfigurationFactory $configurationFactory,
        private readonly Context $context,
        private readonly AssetCollector $assetCollector,
    ) {
    }

    /**
     * Action list
     */
    public function listAction(): ResponseInterface
    {
        if (!$this->isSiteSelected()) {
            return $this->moduleTemplateFactory
                ->create($this->request)
                ->renderResponse('Asset/List');
        }

        $moduleTemplate = $this->moduleTemplateFactory->create($this->request);
        $compilationContext = $this->buildCompilationContext();
        $configurationResult = $this->configurationFactory
            ->buildFromRequest($this->request, $compilationContext);
        if ($configurationResult->isOk()) {
            $assetCollection = $this->assetCollector->collectAssets(
                $configurationResult->unwrap()
            );
            if (!empty($assetCollection->all())) {
                $moduleTemplate->assign('assets', $assetCollection);
            } else {
                $this->addFlashMessage(
                    'No assets found',
                    '',
                    ContextualFeedbackSeverity::WARNING
                );
            }
            $moduleTemplate->assign(
                'lastBuildError',
                $this->sessionService->getErrorFromSession()
            );
        } else {
            $this->addFlashMessage(
                $configurationResult->unwrapErr()->getMessage(),
                '',
                ContextualFeedbackSeverity::ERROR
            );
        }

        return $moduleTemplate->renderResponse('Asset/List');
    }

    /**
     * Action that compiles the stylesheet
     */
    public function compileAction(bool $clearPageCache = false): ResponseInterface
    {
        if (!$this->isSiteSelected()) {
            return $this->moduleTemplateFactory
                ->create($this->request)
                ->renderResponse('Asset/List');
        }

        $this->compile($clearPageCache);

        return $this->redirect('list');
    }

    /**
     * Compile the assets
     */
    private function compile(bool $clearPageCache): void
    {
        $compilationContext = $this->buildCompilationContext();
        $configuration = $this->configurationFactory
            ->buildFromRequest($this->request, $compilationContext)
            ->unwrap();

        assert($compilationContext->forceCompilation);
        $result = $this->manager->collectAndCompile(
            $configuration,
            $compilationContext
        );
        if ($result->isOk()) {
            /** @var FilePath $outputFilePath */
            $outputFilePath = $result->unwrap();
            $this->addFlashMessage('Stylesheets have been compiled to ' . $outputFilePath->getPublicUri());
            $this->sessionService->clearErrorInSession();

            if ($clearPageCache) {
                $this->cacheManager->flushCachesInGroup('pages');
            }
        } else {
            $exception = $result->unwrapErr();
            $this->sessionService->storeErrorInSession($exception->getMessage());

            $message = 'Could not compile files' . ($exception->getCode() > 0 ? ': #' . $exception->getCode() : '');
            $this->addFlashMessage(
                $message,
                '',
                ContextualFeedbackSeverity::ERROR
            );
        }
    }

    private function buildCompilationContext(): CompilationContext
    {
        return new CompilationContext(
            site: $this->request->getAttribute('site'),
            isBackendUserLoggedIn: $this->context->getPropertyFromAspect(
                'backend.user',
                'isLoggedIn'
            ),
            isCliEnvironment: false,
            forceCompilation: true
        );
    }

    private function isSiteSelected(): bool
    {
        return !$this->request->getAttribute('site') instanceof NullSite;
    }
}
