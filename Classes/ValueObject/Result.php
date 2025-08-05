<?php

declare(strict_types=1);

namespace Cundd\Assetic\ValueObject;

use Cundd\Assetic\ValueObject\Result\Err;
use Cundd\Assetic\ValueObject\Result\Ok;
use Throwable;

/**
 * @template T
 * @template E of Throwable
 *
 * @extends AbstractResult<T,E>
 */
abstract class Result extends AbstractResult
{
    /**
     * @template R
     *
     * @param R $inner
     *
     * @return Ok<R>
     */
    public static function ok(mixed $inner): Result
    {
        return new Ok($inner);
    }

    /**
     * @template TE of Throwable
     *
     * @param TE $inner
     *
     * @return Err<TE>
     */
    public static function err(Throwable $inner): Result
    {
        return new Err($inner);
    }
}
