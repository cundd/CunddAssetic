<?php
declare(strict_types=1);

namespace Cundd\Assetic\ValueObject\BuildStateResult;

use Cundd\Assetic\ValueObject\BuildState;
use Cundd\Assetic\ValueObject\BuildStateResult;
use RuntimeException;
use Throwable;

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
        return $this->inner;
    }

    public function unwrapErr(): Throwable
    {
        throw new RuntimeException('Tried to unwrap an error in Ok');
    }
}
