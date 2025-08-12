<?php

declare(strict_types=1);

namespace Cundd\Assetic\Service;

use Psr\Http\Message\ServerRequestInterface;

interface LiveReloadServiceInterface
{
    public function loadLiveReloadCodeIfEnabled(
        ServerRequestInterface $request,
    ): string;
}
