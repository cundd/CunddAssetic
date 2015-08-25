<?php
namespace Cundd\Assetic\Controller;

/*
 *  Copyright notice
 *
 *  (c) 2012 Daniel Corn <info@cundd.net>, cundd
 *
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 */

use Cundd\Assetic\Manager;
use Cundd\Assetic\ManagerInterface;
use Cundd\CunddComposer\Autoloader;
use TYPO3\CMS\Core\Messaging\FlashMessage;
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
        $assetCollection = array();
        $manager         = $this->getManager();

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
     * action show
     *
     * @return void
     */
    public function compileAction()
    {
        $manager = $this->getManager();
        if ($manager) {
            $manager->forceCompile();

            try {
                $outputFileLink = $manager->collectAndCompile();
                $manager->clearHashCache();
                if (defined('TYPO3_MODE') && TYPO3_MODE === 'BE') {
                    $outputFileLink = '../' . $outputFileLink;
                }
                $outputFileLink = '<a href="' . $outputFileLink . '" target="_blank">' . $manager->getOutputFilePath() . '</a>';
                $this->addFlashMessage('Stylesheets have been compiled to ' . $outputFileLink);
            } catch (\Exception $exception) {
                $this->addFlashMessage('Could not compile files: #' . $exception->getCode() . ': ' . $exception->getMessage(),
                    '', FlashMessage::ERROR);
            }
        }
        $this->pd($manager);
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
                $this->addFlashMessage('Make sure the static template is included', 'No configuration found',
                    FlashMessage::WARNING);
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
        $severity = \TYPO3\CMS\Core\Messaging\AbstractMessage::OK,
        $storeInSession = true
    ) {
        if (!is_string($messageBody)) {
            throw new \InvalidArgumentException('The message body must be of type string, "' . gettype($messageBody) . '" given.',
                1243258395);
        }
        /* @var \TYPO3\CMS\Core\Messaging\FlashMessage $flashMessage */
        $flashMessage = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(
            'TYPO3\\CMS\\Core\\Messaging\\FlashMessage', $messageBody, $messageTitle, $severity, $storeInSession
        );
        $this->controllerContext->getFlashMessageQueue()->enqueue($flashMessage);
    }

    /**
     * Dumps a given variable (or the given variables) wrapped into a 'pre' tag.
     *
     * @param    mixed $var1
     * @return    string The printed content
     */
    public function pd($var1 = '__iresults_pd_noValue')
    {
        if (class_exists('Tx_Iresults')) {
            $arguments = func_get_args();
            call_user_func_array(array('Tx_Iresults', 'pd'), $arguments);
        }
    }
}
