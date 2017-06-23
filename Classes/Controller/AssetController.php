<?php

namespace Cundd\Assetic\Controller;

use Cundd\Assetic\Manager;
use Cundd\Assetic\ManagerInterface;
use Cundd\CunddComposer\Autoloader;
use TYPO3\CMS\Core\Messaging\AbstractMessage;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;

Autoloader::register();

/**
 *
 *
 * @package assetic
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License, version 3 or later
 *
 */
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
     * @var \TYPO3\CMS\Extbase\Property\PropertyMapper
     * @inject
     */
    protected $propertyMapper;

    /**
     * The property mapping configuration builder
     *
     * @var \TYPO3\CMS\Extbase\Property\PropertyMappingConfigurationBuilder
     * @inject
     */
    protected $propertyMappingConfigurationBuilder;

    /**
     * action list
     *
     * @return void
     */
    public function listAction()
    {
        $assetCollection = [];
        $manager = $this->getManager();

        $this->pd($manager);

        if ($manager) {
            $manager->collectAssets();
            if ($manager->getCompiler()->getAssetManager()->has('cundd_assetic')) {
                $assetCollection = $manager->getCompiler()->getAssetManager()->get('cundd_assetic');
            }
            if (!empty($assetCollection)) {
                $this->pd($assetCollection);
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
     * Returns a compiler instance with the configuration
     *
     * @return ManagerInterface
     */
    public function getManager()
    {
        if (!$this->manager) {
            $allConfiguration = $this->configurationManager->getConfiguration(
                ConfigurationManagerInterface::CONFIGURATION_TYPE_FULL_TYPOSCRIPT
            );
            if (isset($allConfiguration['plugin.']) && isset($allConfiguration['plugin.']['CunddAssetic.'])) {
                $configuration = $allConfiguration['plugin.']['CunddAssetic.'];
                $this->manager = new Manager($configuration);
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
     * Creates a Message object and adds it to the FlashMessageQueue.
     *
     * @param string $messageBody    The message
     * @param string $messageTitle   Optional message title
     * @param int    $severity       Optional severity, must be one of \TYPO3\CMS\Core\Messaging\FlashMessage constants
     * @param bool   $storeInSession Optional, defines whether the message should be stored in the session (default) or not
     * @return void
     * @throws \InvalidArgumentException if the message body is no string
     * @see \TYPO3\CMS\Core\Messaging\FlashMessage
     * @api
     */
    public function addFlashMessage(
        $messageBody,
        $messageTitle = '',
        $severity = AbstractMessage::OK,
        $storeInSession = true
    ) {
        if (!is_string($messageBody)) {
            throw new \InvalidArgumentException(
                'The message body must be of type string, "' . gettype($messageBody) . '" given.',
                1243258395
            );
        }
        /* @var \TYPO3\CMS\Core\Messaging\FlashMessage $flashMessage */
        $flashMessage = GeneralUtility::makeInstance(
            'TYPO3\\CMS\\Core\\Messaging\\FlashMessage',
            $messageBody,
            $messageTitle,
            $severity,
            $storeInSession
        );
        $this->controllerContext->getFlashMessageQueue()->enqueue($flashMessage);
    }

    /**
     * Dumps a given variable (or the given variables) wrapped into a 'pre' tag.
     *
     * @param mixed $var1
     */
    public function pd($var1 = '__iresults_pd_noValue')
    {
        if (class_exists('Tx_Iresults')) {
            $arguments = func_get_args();
            call_user_func_array(['Tx_Iresults', 'pd'], $arguments);
        }
    }

    /**
     * Compile the assets
     *
     * @param bool $clearPageCache
     */
    private function compile($clearPageCache)
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
            } catch (\Exception $exception) {
                $this->addFlashMessage(
                    'Could not compile files: #' . $exception->getCode() . ': ' . $exception->getMessage(),
                    '',
                    FlashMessage::ERROR
                );
            }
        }
        $this->pd($manager);
    }
}
