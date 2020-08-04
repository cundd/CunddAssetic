<?php
declare(strict_types=1);

namespace Cundd\Assetic\Controller;

use Cundd\Assetic\Configuration\ConfigurationProvider;
use Cundd\Assetic\Manager;
use Cundd\Assetic\ManagerInterface;
use Cundd\CunddComposer\Autoloader;
use Exception;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Extbase\Property\PropertyMapper;
use TYPO3\CMS\Extbase\Property\PropertyMappingConfigurationBuilder;

Autoloader::register();

class AssetController extends ActionController
{
    /**
     * Asset manager instance
     *
     * @var ManagerInterface
     */
    protected $manager;

    /**
     * The property mapper
     *
     * @var PropertyMapper
     */
    protected $propertyMapper;

    /**
     * The property mapping configuration builder
     *
     * @var PropertyMappingConfigurationBuilder
     */
    protected $propertyMappingConfigurationBuilder;

    /**
     * Asset Controller constructor
     *
     * @param PropertyMapper                      $propertyMapper
     * @param PropertyMappingConfigurationBuilder $propertyMappingConfigurationBuilder
     * @param ManagerInterface|null               $manager
     */
    public function __construct(
        PropertyMapper $propertyMapper,
        PropertyMappingConfigurationBuilder $propertyMappingConfigurationBuilder,
        ManagerInterface $manager = null
    ) {
        parent::__construct();
        $this->propertyMapper = $propertyMapper;
        $this->propertyMappingConfigurationBuilder = $propertyMappingConfigurationBuilder;
        $this->manager = $manager;
    }

    /**
     * Action list
     *
     * @return void
     */
    public function listAction()
    {
        $assetCollection = [];
        $manager = $this->getManager();

        if ($manager) {
            $manager->collectAssets();
            if ($manager->getCompiler()->getAssetManager()->has('cundd_assetic')) {
                $assetCollection = $manager->getCompiler()->getAssetManager()->get('cundd_assetic');
            }
            if (!empty($assetCollection)) {
                $this->view->assign('assets', $assetCollection);
            } else {
                $this->addFlashMessage('No assets found');
            }
        }
    }

    /**
     * Action that compiles the stylesheet
     *
     * @param bool $clearPageCache
     */
    public function compileAction($clearPageCache = false)
    {
        $this->compile($clearPageCache);
        $this->redirect('list');
    }

    /**
     * Return a Compiler Manager instance with the configuration
     *
     * @return ManagerInterface
     */
    public function getManager(): ?ManagerInterface
    {
        if (!$this->manager) {
            $allConfiguration = $this->configurationManager->getConfiguration(
                ConfigurationManagerInterface::CONFIGURATION_TYPE_FULL_TYPOSCRIPT
            );
            if (isset($allConfiguration['plugin.']) && isset($allConfiguration['plugin.']['CunddAssetic.'])) {
                $configuration = $allConfiguration['plugin.']['CunddAssetic.'];
                $this->manager = new Manager(new ConfigurationProvider($configuration));
            } else {
                $this->addFlashMessage(
                    'Make sure the static template is included',
                    'No configuration found',
                    FlashMessage::WARNING
                );
            }
        }

        return $this->manager;
    }

    /**
     * Compile the assets
     *
     * @param bool $clearPageCache
     */
    private function compile(bool $clearPageCache): void
    {
        $manager = $this->getManager();
        if ($manager) {
            $manager->forceCompile();

            try {
                $manager->collectAndCompile();
                $manager->clearHashCache();
                $this->addFlashMessage(
                    'Stylesheets have been compiled to ' . $manager->getOutputFilePath()
                );

                if ($clearPageCache) {
                    $this->cacheService->clearPageCache();
                }
            } catch (Exception $exception) {
                $this->addFlashMessage(
                    'Could not compile files: #' . $exception->getCode() . ': ' . $exception->getMessage(),
                    '',
                    FlashMessage::ERROR
                );
            }
        }
    }
}
