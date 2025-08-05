<?php

declare(strict_types=1);

namespace Cundd\Assetic\ValueObject\Result;

use Cundd\Assetic\ValueObject\Result;
use RuntimeException;
use Throwable;

/**
 * @template E of Throwable
 *
 * @extends Result<never,E>
 */
class Err extends Result
{
    /**
     * @param E $error
     */
    public function __construct(Throwable $error)
    {
        parent::__construct($error);
    }

    public function isOk(): bool
    {
        return false;
    }

    public function unwrap(): mixed
    {
        throw new RuntimeException('Tried to unwrap an Err', 6562984701);
    }

    public function unwrapErr(): Throwable
    {
        return $this->inner;
    }
}
