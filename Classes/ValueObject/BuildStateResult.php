<?php

declare(strict_types=1);

namespace Cundd\Assetic\ValueObject;

use Cundd\Assetic\ValueObject\BuildStateResult\BuildErr;
use Cundd\Assetic\ValueObject\BuildStateResult\BuildOk;
use Throwable;

/**
 * @template E of Throwable
 *
 * @extends AbstractResult<BuildState,E>
 */
abstract class BuildStateResult extends AbstractResult
{
    public static function ok(BuildState $inner): BuildOk
    {
        return new BuildOk($inner);
    }

    /**
     * @template R of Throwable
     *
     * @param R $inner
     *
     * @return BuildErr<R>
     */
    public static function err(Throwable $inner): BuildErr
    {
        return new BuildErr($inner);
    }
}
