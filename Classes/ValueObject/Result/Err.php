<?php
declare(strict_types=1);

namespace Cundd\Assetic\ValueObject\Result;

use Cundd\Assetic\ValueObject\Result;
use RuntimeException;
use Throwable;

class Err extends Result
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
        throw new RuntimeException('Tried to unwrap an Err');
    }

    public function unwrapErr(): Throwable
    {
        return $this->inner;
    }
}
