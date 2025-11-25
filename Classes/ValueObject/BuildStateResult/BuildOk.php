<?php

declare(strict_types=1);

namespace Cundd\Assetic\ValueObject\BuildStateResult;

use Cundd\Assetic\ValueObject\BuildState;
use Cundd\Assetic\ValueObject\BuildStateResult;
use Override;
use RuntimeException;

/**
 * @extends BuildStateResult<never>
 */
class BuildOk extends BuildStateResult
{
    public function __construct(BuildState $buildState)
    {
        parent::__construct($buildState);
    }

    #[Override]
    public function isOk(): bool
    {
        return true;
    }

    #[Override]
    public function unwrap(): BuildState
    {
        assert($this->isOk());

        return $this->inner;
    }

    #[Override]
    public function unwrapErr(): never
    {
        throw new RuntimeException('Tried to unwrap an error in Ok', 6811690505);
    }
}
