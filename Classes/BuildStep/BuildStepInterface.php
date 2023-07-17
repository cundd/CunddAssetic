<?php

declare(strict_types=1);

namespace Cundd\Assetic\BuildStep;

use Cundd\Assetic\ValueObject\BuildState;
use Cundd\Assetic\ValueObject\BuildStateResult;

interface BuildStepInterface
{
    public function process(BuildState $currentState): BuildStateResult;
}
