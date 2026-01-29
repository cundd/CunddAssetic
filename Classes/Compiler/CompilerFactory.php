<?php

declare(strict_types=1);

namespace Cundd\Assetic\Compiler;

class CompilerFactory
{
    public function __construct(private readonly AssetCollector $assetCollector)
    {
    }

    public function build(): CompilerInterface
    {
        return new Compiler($this->assetCollector);
    }
}
