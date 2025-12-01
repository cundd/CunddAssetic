<?php

declare(strict_types=1);

namespace Cundd\Assetic\Compiler;

class CompilerFactory
{
    public function build(): CompilerInterface
    {
        return new Compiler();
    }
}
