<?php

declare(strict_types=1);

namespace Cundd\Assetic\Controller;

use Cundd\Assetic\ManagerInterface;
use Cundd\Assetic\Service\SessionServiceInterface;
use Cundd\Assetic\Utility\Autoloader;
use Cundd\Assetic\ValueObject\FilePath;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;

class AssetController extends ActionController
{
    private ManagerInterface $manager;

    private CacheManager $cacheManager;

    private SessionServiceInterface $sessionService;

    private ModuleTemplateFactory $moduleTemplateFactory;

    public function __construct(
        ManagerInterface $manager,
        CacheManager $cacheManager,
        SessionServiceInterface $sessionService,
        ModuleTemplateFactory $moduleTemplateFactory,
    ) {
        Autoloader::register();
        $this->manager = $manager;
        $this->cacheManager = $cacheManager;
        $this->sessionService = $sessionService;
        $this->moduleTemplateFactory = $moduleTemplateFactory;
    }

    /**
     * Action list
     */
    public function listAction(): ResponseInterface
    {
        $moduleTemplate = $this->moduleTemplateFactory->create($this->request);
        $assetCollection = [];
        $this->manager->collectAssets();
        if ($this->manager->getCompiler()->getAssetManager()->has('cundd_assetic')) {
            $assetCollection = $this->manager->getCompiler()->getAssetManager()->get('cundd_assetic');
        }
        if (!empty($assetCollection)) {
            $this->view->assign('assets', $assetCollection);
        } else {
            $this->addFlashMessage('No assets found');
        }
        $this->view->assign('lastBuildError', $this->sessionService->getErrorFromSession());

        $moduleTemplate->setContent($this->view->render());

        return $this->htmlResponse($moduleTemplate->renderContent());
    }

    /**
     * Action that compiles the stylesheet
     *
     * @param bool $clearPageCache
     * @return ResponseInterface
     */
    public function compileAction(bool $clearPageCache = false): ResponseInterface
    {
        $this->compile($clearPageCache);

        return $this->redirect('list');
    }

    /**
     * Compile the assets
     *
     * @param bool $clearPageCache
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
                FlashMessage::ERROR
            );
        }
    }
}
