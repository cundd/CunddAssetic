<?php

declare(strict_types=1);

namespace Cundd\Assetic\ValueObject;

use Cundd\Assetic\ValueObject\Result\Ok;

/**
 * @template T
 * @template E
 */
abstract class AbstractResult
{
    /**
     * @var T|E
     */
    protected readonly mixed $inner;

    /**
     * @param T|E $inner
     */
    protected function __construct(mixed $inner)
    {
        $this->inner = $inner;
    }

    /**
     * @phpstan-assert-if-true =T $this->inner
     *
     * @phpstan-assert-if-false =E $this->inner
     */
    abstract public function isOk(): bool;

    /**
     * @phpstan-assert-if-true =E $this->inner
     *
     * @phpstan-assert-if-false =T $this->inner
     */
    public function isErr(): bool
    {
        return !$this->isOk();
    }

    /**
     * @return T
     */
    abstract public function unwrap(): mixed;

    /**
     * @return E
     */
    abstract public function unwrapErr(): mixed;

    /**
     * Invoke `$ok()` if this instance is `Ok`. Invoke `$err()` if this instance is `Err`
     *
     * @template R
     * @template X
     *
     * @param callable(T): R $ok
     * @param callable(E): X $err
     *
     * @return R|X
     */
    public function doMatch(callable $ok, callable $err): mixed
    {
        if ($this->isOk()) {
            return $ok($this->inner);
        } else {
            return $err($this->inner);
        }
    }

    /**
     * @template R
     *
     * @param callable(T): R $callback
     *
     * @return Ok<R>
     */
    public function map(callable $callback): Ok
    {
        return new Ok($callback($this->unwrap()));
    }
}
