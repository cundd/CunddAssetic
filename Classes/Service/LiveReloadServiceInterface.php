<?php

declare(strict_types=1);

namespace Cundd\Assetic\Service;

use Cundd\Assetic\Configuration;
use Cundd\Assetic\ValueObject\CompilationContext;
use Psr\Http\Message\ServerRequestInterface;

interface LiveReloadServiceInterface
{
    public function loadLiveReloadCodeIfEnabled(
        ServerRequestInterface $request,
        Configuration $configuration,
        CompilationContext $compilationContext,
    ): string;
}
