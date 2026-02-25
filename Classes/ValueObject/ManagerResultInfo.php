<?php

declare(strict_types=1);

namespace Cundd\Assetic\ValueObject;

final class ManagerResultInfo
{
    public function __construct(
        public readonly FilePath $filePath,
        public readonly bool $usedExistingFile,
    ) {
    }
}
