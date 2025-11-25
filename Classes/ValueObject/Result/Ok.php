<?php

declare(strict_types=1);

namespace Cundd\Assetic\ValueObject\Result;

use Cundd\Assetic\ValueObject\Result;
use Override;
use RuntimeException;

/**
 * @template U
 *
 * @extends Result<U,never>
 */
class Ok extends Result
{
    /**
     * @param U $value
     */
    public function __construct(mixed $value)
    {
        parent::__construct($value);
    }

    #[Override]
    public function isOk(): bool
    {
        return true;
    }

    /**
     * @return U
     */
    #[Override]
    public function unwrap(): mixed
    {
        assert($this->isOk());

        return $this->inner;
    }

    #[Override]
    public function unwrapErr(): never
    {
        throw new RuntimeException('Tried to unwrap an error in Ok', 9462380717);
    }
}
