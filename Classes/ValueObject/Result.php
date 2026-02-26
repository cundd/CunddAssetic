<?php

declare(strict_types=1);

namespace Cundd\Assetic\ValueObject;

use Cundd\Assetic\ValueObject\Result\Err;
use Cundd\Assetic\ValueObject\Result\Ok;
use Throwable;

/**
 * @template T
 * @template E of Throwable
 */
abstract class Result
{
    /**
     * @use ResultTrait<T,E>
     */
    use ResultTrait;

    /**
     * @param T|E $inner
     */
    protected function __construct(protected readonly mixed $inner)
    {
    }

    /**
     * @template R
     *
     * @param R $inner
     *
     * @return self<R,never>
     */
    public static function ok(mixed $inner): self
    {
        return new Ok($inner);
    }

    /**
     * @template TE of Throwable
     *
     * @param TE $inner
     *
     * @return self<never,TE>
     */
    public static function err(Throwable $inner): self
    {
        return new Err($inner);
    }

    /**
     * @template R
     *
     * @param callable(T): R $callback
     *
     * @return self<R,E>
     */
    public function map(callable $callback): self
    {
        if ($this->isOk()) {
            return static::ok($callback($this->inner));
        } else {
            return static::err($this->inner);
        }
    }
}
