<?php

declare(strict_types=1);

namespace Cundd\Assetic\ValueObject;

use Throwable;

/**
 * @template-implements BuildState
 * @template E extends Throwable
 */
abstract class BuildStateResult extends AbstractResult
{
    public static function ok(BuildState $inner): BuildStateResult
    {
        return new BuildStateResult\Ok($inner);
    }

    public static function err(Throwable $inner): BuildStateResult
    {
        return new BuildStateResult\Err($inner);
    }
}
