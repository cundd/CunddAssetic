<?php

declare(strict_types=1);

namespace Cundd\Assetic\Exception;

use Cundd\Assetic\ValueObject\Result\Err;
use RuntimeException;

class ConfigurationException extends RuntimeException
{
    /**
     * @return Err<$this>
     */
    public function intoErr(): Err
    {
        return new Err($this);
    }
}
