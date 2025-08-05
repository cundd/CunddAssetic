<?php

declare(strict_types=1);

namespace Cundd\Assetic\Controller;

use Cundd\Assetic\ManagerInterface;
use Cundd\Assetic\Service\SessionServiceInterface;
use Cundd\Assetic\ValueObject\FilePath;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Backend\Attribute\AsController;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Core\Cache\CacheManager;
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
    ) {
    }

    /**
     * Action list
     */
    public function listAction(): ResponseInterface
    {
        $moduleTemplate = $this->moduleTemplateFactory->create($this->request);
        $assetCollection = $this->manager->collectAssets();
        if (!empty($assetCollection->all())) {
            $moduleTemplate->assign('assets', $assetCollection);
        } else {
            $this->addFlashMessage('No assets found', '', ContextualFeedbackSeverity::WARNING);
        }
        $moduleTemplate->assign('lastBuildError', $this->sessionService->getErrorFromSession());

        return $moduleTemplate->renderResponse('Asset/List');
    }

    /**
     * Action that compiles the stylesheet
     */
    public function compileAction(bool $clearPageCache = false): ResponseInterface
    {
        $this->compile($clearPageCache);

        return $this->redirect('list');
    }

    /**
     * Compile the assets
     */
    private function compile(bool $clearPageCache): void
    {
        $result = $this->manager->forceCompile()->collectAndCompile();
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
}
