<?php

declare(strict_types=1);

namespace Cundd\Assetic\ValueObject;

use Throwable;

/**
 * @template T
 * @template E extends Throwable
 *
 * @extends AbstractResult<T,E>
 */
abstract class Result extends AbstractResult
{
    public static function ok(mixed $inner): Result
    {
        return new Result\Ok($inner);
    }

    public static function err(Throwable $inner): Result
    {
        return new Result\Err($inner);
    }
}
