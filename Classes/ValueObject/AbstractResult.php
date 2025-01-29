<?php

declare(strict_types=1);

namespace Cundd\Assetic\ValueObject;

use Throwable;

/**
 * @template T
 * @template E of Throwable
 */
abstract class AbstractResult
{
    /**
     * @var T|E
     */
    protected $inner;

    /**
     * @param T|E $inner
     */
    protected function __construct($inner)
    {
        $this->inner = $inner;
    }

    abstract public function isOk(): bool;

    public function isErr(): bool
    {
        return !$this->isOk();
    }

    /**
     * @return T
     */
    abstract public function unwrap();

    /**
     * @return E
     */
    abstract public function unwrapErr(): Throwable;

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
}
