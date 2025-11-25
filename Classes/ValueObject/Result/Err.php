<?php

declare(strict_types=1);

namespace Cundd\Assetic\ValueObject\Result;

use Cundd\Assetic\ValueObject\Result;
use Override;
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

    #[Override]
    public function isOk(): bool
    {
        return false;
    }

    #[Override]
    public function unwrap(): never
    {
        throw new RuntimeException('Tried to unwrap an Err', 6562984701);
    }

    #[Override]
    public function unwrapErr(): Throwable
    {
        assert($this->isErr());

        return $this->inner;
    }
}
