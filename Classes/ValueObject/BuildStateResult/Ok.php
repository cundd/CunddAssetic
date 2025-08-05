<?php

declare(strict_types=1);

namespace Cundd\Assetic\ValueObject\BuildStateResult;

use Cundd\Assetic\ValueObject\BuildState;
use Cundd\Assetic\ValueObject\BuildStateResult;
use RuntimeException;
use Throwable;

/**
 * @extends BuildStateResult<never>
 */
class Ok extends BuildStateResult
{
    public function __construct(BuildState $buildState)
    {
        parent::__construct($buildState);
    }

    public function isOk(): bool
    {
        return true;
    }

    public function unwrap(): BuildState
    {
        assert($this->isOk());

        return $this->inner;
    }

    public function unwrapErr(): Throwable
    {
        throw new RuntimeException('Tried to unwrap an error in Ok', 6811690505);
    }
}
