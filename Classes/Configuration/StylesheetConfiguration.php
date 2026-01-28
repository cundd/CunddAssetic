<?php

declare(strict_types=1);

namespace Cundd\Assetic\Configuration;

/**
 * @phpstan-type FilterArgument string|int|float|bool
 */
final class StylesheetConfiguration
{
    /**
     * @param array<non-empty-string, FilterArgument> $functions
     */
    public function __construct(
        public readonly string $file,
        public readonly array $functions,
        public readonly ?string $type,
    ) {
    }
}
