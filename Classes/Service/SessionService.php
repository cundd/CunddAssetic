<?php

declare(strict_types=1);

namespace Cundd\Assetic\Service;

use TYPO3\CMS\Core\Authentication\AbstractUserAuthentication;
use UnexpectedValueException;

class SessionService implements SessionServiceInterface
{
    private const BUILD_ERROR_KEY = 'cundd_assetic_build_error';

    public function storeErrorInSession(string $error)
    {
        $this->getUser()->setAndSaveSessionData(self::BUILD_ERROR_KEY, $error);
    }

    public function getErrorFromSession(): ?string
    {
        return $this->getUser()->getSessionData(self::BUILD_ERROR_KEY);
    }

    public function clearErrorInSession()
    {
        $this->getUser()->setAndSaveSessionData(self::BUILD_ERROR_KEY, null);
    }

    private function getUser(): AbstractUserAuthentication
    {
        if (!isset($GLOBALS['BE_USER'])) {
            throw new UnexpectedValueException('No valid backend user found');
        }

        return $GLOBALS['BE_USER'];
    }
}
