<?php

declare(strict_types=1);

namespace Cundd\Assetic\ValueObject\Result;

use Cundd\Assetic\ValueObject\Result;
use RuntimeException;
use Throwable;

/**
 * @template T
 *
 * @extends Result<T,never>
 */
class Ok extends Result
{
    /**
     * @param T $value
     */
    public function __construct(mixed $value)
    {
        parent::__construct($value);
    }

    public function isOk(): bool
    {
        return true;
    }

    /**
     * @return T
     */
    public function unwrap(): mixed
    {
        assert($this->isOk());

        return $this->inner;
    }

    public function unwrapErr(): Throwable
    {
        throw new RuntimeException('Tried to unwrap an error in Ok', 9462380717);
    }
}
