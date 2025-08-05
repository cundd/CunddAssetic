<?php

declare(strict_types=1);

namespace Cundd\Assetic\BuildStep;

use Cundd\Assetic\Service\SymlinkServiceInterface;
use Cundd\Assetic\ValueObject\BuildState;
use Cundd\Assetic\ValueObject\BuildStateResult;
use Throwable;

/**
 * @implements BuildStepInterface<Throwable>
 */
class RemoveOldSymlinks implements BuildStepInterface
{
    public function __construct(private readonly SymlinkServiceInterface $symlinkService)
    {
    }

    public function process(BuildState $currentState): BuildStateResult
    {
        $this->symlinkService->removeSymlink($currentState->getOutputFilePathWithoutHash());

        return BuildStateResult::ok($currentState);
    }
}
