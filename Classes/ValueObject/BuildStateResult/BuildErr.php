<?php

declare(strict_types=1);

namespace Cundd\Assetic\ValueObject\BuildStateResult;

use Cundd\Assetic\ValueObject\BuildStateResult;
use Override;
use RuntimeException;
use Throwable;

/**
 * @template E of Throwable
 *
 * @extends BuildStateResult<E>
 */
class BuildErr extends BuildStateResult
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
        throw new RuntimeException('Tried to unwrap an Err', 9949823916);
    }

    #[Override]
    public function unwrapErr(): Throwable
    {
        assert($this->isErr());

        return $this->inner;
    }
}
