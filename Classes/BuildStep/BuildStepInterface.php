<?php

declare(strict_types=1);

namespace Cundd\Assetic\BuildStep;

use Cundd\Assetic\ValueObject\BuildState;
use Cundd\Assetic\ValueObject\BuildStateResult;

/**
 * @template E of \Throwable
 */
interface BuildStepInterface
{
    /**
     * @return BuildStateResult<E>
     */
    public function process(BuildState $currentState): BuildStateResult;
}
