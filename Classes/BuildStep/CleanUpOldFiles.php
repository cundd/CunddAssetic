<?php

declare(strict_types=1);

namespace Cundd\Assetic\BuildStep;

use Cundd\Assetic\Configuration;
use Cundd\Assetic\ValueObject\BuildState;
use Cundd\Assetic\ValueObject\BuildStateResult;
use Throwable;

use function unlink;

/**
 * @implements BuildStepInterface<Throwable>
 */
class CleanUpOldFiles implements BuildStepInterface
{
    public function process(
        Configuration $configuration,
        BuildState $currentState,
    ): BuildStateResult {
        $matchingFiles = $currentState->getFilesToCleanUp();
        foreach ($matchingFiles as $oldFilteredAssetFile) {
            @unlink($oldFilteredAssetFile);
        }

        return BuildStateResult::ok($currentState);
    }
}
