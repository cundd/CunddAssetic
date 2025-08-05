<?php

declare(strict_types=1);

namespace Cundd\Assetic\BuildStep;

use Cundd\Assetic\Compiler\CompilerInterface;
use Cundd\Assetic\ValueObject\BuildState;
use Cundd\Assetic\ValueObject\BuildStateResult;
use Throwable;

/**
 * @implements BuildStepInterface<Throwable>
 */
class Compile implements BuildStepInterface
{
    public function __construct(private readonly CompilerInterface $compiler)
    {
    }

    public function process(BuildState $currentState): BuildStateResult
    {
        $result = $this->compiler->compile();

        return $result->isOk()
            ? BuildStateResult::ok($currentState)
            : BuildStateResult::err($result->unwrapErr());
    }
}
