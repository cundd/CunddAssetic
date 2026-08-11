<?php

declare(strict_types=1);

namespace Cundd\Assetic\BuildSystem;

use Cundd\Assetic\Configuration;
use Cundd\Assetic\ValueObject\BuildStateResult;
use Throwable;

interface ExecutorInterface
{
    /**
     * Configure and execute the build steps
     *
     * @return BuildStateResult<covariant Throwable>
     */
    public function build(
        Configuration $configuration,
    ): BuildStateResult;
}
