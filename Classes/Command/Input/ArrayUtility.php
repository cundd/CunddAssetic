<?php

declare(strict_types=1);

namespace Cundd\Assetic\Command\Input;

use function array_push;
use function explode;

class ArrayUtility
{
    /**
     * Normalize the given string array
     *
     * @param string[] $rawInput
     * @return string[]
     */
    public static function normalizeInput(array $rawInput): array
    {
        $normalizedInput = [];
        foreach ($rawInput as $item) {
            array_push($normalizedInput, ...explode(',', $item));
        }

        return $normalizedInput;
    }
}
