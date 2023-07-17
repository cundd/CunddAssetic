<?php

declare(strict_types=1);

namespace Cundd\Assetic\ValueObject\Result;

use Cundd\Assetic\ValueObject\Result;
use RuntimeException;
use Throwable;

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

    public function unwrap()
    {
        return $this->inner;
    }

    public function unwrapErr(): Throwable
    {
        throw new RuntimeException('Tried to unwrap an error in Ok');
    }
}
