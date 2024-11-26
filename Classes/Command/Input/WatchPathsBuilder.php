<?php

declare(strict_types=1);

namespace Cundd\Assetic\Command\Input;

use Cundd\Assetic\Exception\FilePathException;
use Cundd\Assetic\Utility\PathUtility;
use Symfony\Component\Console\Input\InputInterface;

use function array_filter;
use function array_map;
use function sprintf;

class WatchPathsBuilder
{
    /**
     * Fetch the argument or option from the given CLI input and parse them as an array of absolute URLs
     *
     * @return string[]
     */
    public function buildPathsFromInput(InputInterface $input, string $name): array
    {
        $rawInput = $this->getRawInput($input, $name);
        $normalizedInput = ArrayUtility::normalizeInput($rawInput);

        return $this->prepareWatchPaths($normalizedInput);
    }

    /**
     * @return string[]
     */
    private function getRawInput(InputInterface $input, string $name): array
    {
        $argument = $input->getArgument($name);

        return $argument ?: $input->getOption($name);
    }

    /**
     * @param string[] $paths
     *
     * @return string[]
     */
    private function prepareWatchPaths(array $paths): array
    {
        return array_map(
            function (string $inputPath) {
                $resolvedPath = PathUtility::getAbsolutePath($inputPath);
                if ('' === $resolvedPath) {
                    throw new FilePathException(sprintf('Watch path "%s" could not be resolved', $inputPath), 6952901911);
                }

                return $resolvedPath;
            },
            array_filter($paths)
        );
    }
}
