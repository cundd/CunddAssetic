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
class CreateNewSymlink implements BuildStepInterface
{
    public function __construct(private readonly SymlinkServiceInterface $symlinkService)
    {
    }

    public function process(BuildState $currentState): BuildStateResult
    {
        $filePath = $this->symlinkService->createSymlinkToFinalPath(
            $currentState->getFilePath(),
            $currentState->getOutputFilePathWithoutHash()
        );

        if (null !== $filePath) {
            return BuildStateResult::ok($currentState->withFilePath($filePath));
        } else {
            return BuildStateResult::ok($currentState);
        }
    }
}
