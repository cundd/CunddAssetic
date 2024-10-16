<?php

declare(strict_types=1);

namespace Cundd\Assetic\Service;

interface SessionServiceInterface
{
    /**
     * Store the given error message in the session
     *
     * @return void
     */
    public function storeErrorInSession(string $error);

    /**
     * Return the last error message from the session
     */
    public function getErrorFromSession(): ?string;

    /**
     * Clear the last error message
     *
     * @return void
     */
    public function clearErrorInSession();
}
