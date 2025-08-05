<?php

declare(strict_types=1);

namespace Cundd\Assetic\ValueObject;

use Cundd\Assetic\ValueObject\BuildStateResult\Err;
use Cundd\Assetic\ValueObject\BuildStateResult\Ok;
use Throwable;

/**
 * @template E of Throwable
 *
 * @extends AbstractResult<BuildState,E>
 */
abstract class BuildStateResult extends AbstractResult
{
    public static function ok(BuildState $inner): Ok
    {
        return new Ok($inner);
    }

    /**
     * @template R of Throwable
     *
     * @param R $inner
     *
     * @return BuildStateResult<R>
     */
    public static function err(Throwable $inner): BuildStateResult
    {
        return new Err($inner);
    }
}
