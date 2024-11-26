<?php

declare(strict_types=1);

namespace Cundd\Assetic\ValueObject\Result;

use Cundd\Assetic\ValueObject\Result;
use RuntimeException;
use Throwable;

/**
 * @template T
 * @template E extends Throwable
 *
 * @extends Result<T,E>
 */
class Ok extends Result
{
    public function __construct($value)
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
        return $this->inner;
    }

    public function unwrapErr(): Throwable
    {
        throw new RuntimeException('Tried to unwrap an error in Ok', 9462380717);
    }
}
