<?php

declare(strict_types=1);

namespace Cundd\Assetic\ValueObject\BuildStateResult;

use Cundd\Assetic\ValueObject\BuildStateResult;
use RuntimeException;
use Throwable;

class Err extends BuildStateResult
{
    public function __construct(Throwable $error)
    {
        parent::__construct($error);
    }

    public function isOk(): bool
    {
        return false;
    }

    public function unwrap()
    {
        throw new RuntimeException('Tried to unwrap an Err', 9949823916);
    }

    public function unwrapErr(): Throwable
    {
        return $this->inner;
    }
}
