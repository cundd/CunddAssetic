<?php

declare(strict_types=1);

namespace Cundd\Assetic\ValueObject;

use Throwable;

/**
 * @template T
 * @template E extends Throwable
 */
abstract class Result extends AbstractResult
{
    public static function ok($inner): Result
    {
        return new Result\Ok($inner);
    }

    public static function err(Throwable $inner): Result
    {
        return new Result\Err($inner);
    }
}
