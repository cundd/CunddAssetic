<?php

declare(strict_types=1);

namespace Cundd\Assetic\BuildStep;

use Cundd\Assetic\Service\SymlinkServiceInterface;
use Cundd\Assetic\ValueObject\BuildState;
use Cundd\Assetic\ValueObject\BuildStateResult;

class CreateNewSymlink implements BuildStepInterface
{
    private SymlinkServiceInterface $symlinkService;

    public function __construct(SymlinkServiceInterface $symlinkService)
    {
        $this->symlinkService = $symlinkService;
    }

    public function process(BuildState $currentState): BuildStateResult
    {
        $this->symlinkService->createSymlinkToFinalPath(
            $currentState->getFilePath(),
            $currentState->getOutputFilePathWithoutHash()
        );

        return BuildStateResult::ok($currentState);
    }
}
