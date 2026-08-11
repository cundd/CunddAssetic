<?php

declare(strict_types=1);

namespace Cundd\Assetic\BuildSystem\BuildStep;

use Cundd\Assetic\Configuration;
use Cundd\Assetic\Output\SymlinkServiceInterface;
use Cundd\Assetic\ValueObject\BuildState;
use Cundd\Assetic\ValueObject\BuildStateResult;
use Throwable;

/**
 * @implements BuildStepInterface<Throwable>
 */
final class RemoveOldSymlinks implements BuildStepInterface
{
    public function __construct(
        private readonly SymlinkServiceInterface $symlinkService,
    ) {
    }

    public function process(
        Configuration $configuration,
        BuildState $currentState,
    ): BuildStateResult {
        $this->symlinkService->removeSymlink(
            $configuration,
            $currentState->getOutputFilePathWithoutHash()
        );

        return BuildStateResult::ok($currentState);
    }
}
