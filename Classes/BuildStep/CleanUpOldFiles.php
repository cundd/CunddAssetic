<?php

declare(strict_types=1);

namespace Cundd\Assetic\BuildStep;

use Cundd\Assetic\ValueObject\BuildState;
use Cundd\Assetic\ValueObject\BuildStateResult;

use function unlink;

class CleanUpOldFiles implements BuildStepInterface
{
    public function process(BuildState $currentState): BuildStateResult
    {
        $matchingFiles = $currentState->getFilesToCleanUp();
        foreach ($matchingFiles as $oldFilteredAssetFile) {
            @unlink($oldFilteredAssetFile);
        }

        return BuildStateResult::ok($currentState);
    }
}
