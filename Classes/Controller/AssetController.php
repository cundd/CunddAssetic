<?php
declare(strict_types=1);

namespace Cundd\Assetic\Controller;

use Cundd\Assetic\ManagerInterface;
use Cundd\Assetic\Service\SessionServiceInterface;
use Cundd\Assetic\Utility\Autoloader;
use Cundd\Assetic\ValueObject\FilePath;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Messaging\AbstractMessage;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;

class AssetController extends ActionController
{
    private ManagerInterface $manager;

    private CacheManager $cacheManager;

    private SessionServiceInterface $sessionService;

    public function __construct(
        ManagerInterface $manager,
        CacheManager $cacheManager,
        SessionServiceInterface $sessionService
    ) {
        Autoloader::register();
        $this->manager = $manager;
        $this->cacheManager = $cacheManager;
        $this->sessionService = $sessionService;
    }

    /**
     * Action list
     *
     * @return void
     */
    public function listAction()
    {
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
    }

    /**
     * Action that compiles the stylesheet
     *
     * @param bool $clearPageCache
     */
    public function compileAction(bool $clearPageCache = false)
    {
        $this->compile($clearPageCache);
        $this->redirect('list');
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
                AbstractMessage::ERROR
            );
        }
    }
}
