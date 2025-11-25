<?php

declare(strict_types=1);

namespace Cundd\Assetic\BuildStep;

use Cundd\Assetic\Exception\OutputFileException;
use Cundd\Assetic\ValueObject\BuildState;
use Cundd\Assetic\ValueObject\BuildStateResult;
use TYPO3\CMS\Core\Resource\Exception\InvalidFileException;
use TYPO3\CMS\Core\Utility\PathUtility;

use function array_unique;
use function file_get_contents;
use function file_put_contents;
use function preg_match_all;
use function sprintf;
use function str_replace;
use function str_starts_with;
use function strpos;

/**
 * Build step to replace occurrences of `EXT:extension_name/Resources/Public/` with the correct `_assets/...` URI
 *
 * @implements BuildStepInterface<OutputFileException>
 */
class PatchExtensionPath implements BuildStepInterface
{
    public function process(BuildState $currentState): BuildStateResult
    {
        $compiledFileUri = $currentState->getFilePath()->getAbsoluteUri();
        $contents = (string) file_get_contents($compiledFileUri);
        if (!preg_match_all('!EXT:([a-zA-Z0-9-_]+/Resources/Public/)!', $contents, $matches)) {
            return BuildStateResult::ok($currentState);
        }

        foreach (array_unique($matches[0]) as $extensionPath) {
            try {
                $contents = str_replace(
                    $extensionPath,
                    $this->getPublicResourceWebPath($extensionPath),
                    $contents
                );
            } catch (InvalidFileException $e) {
                $errorMessage = sprintf(
                    'Could not write patched output file to destination: "%s", because %s',
                    $compiledFileUri,
                    $e->getMessage()
                );

                return BuildStateResult::err(new OutputFileException($errorMessage, $e->getCode(), $e));
            }
        }

        if (false === file_put_contents($compiledFileUri, $contents)) {
            return BuildStateResult::err(
                new OutputFileException(
                    sprintf('Could not write patched output file to destination: "%s"', $compiledFileUri)
                )
            );
        }

        return BuildStateResult::ok($currentState);
    }

    private function getPublicResourceWebPath(string $extensionPath): string
    {
        $path = PathUtility::getPublicResourceWebPath($extensionPath);

        $looksLikeFullUri = str_starts_with($path, '//') || strpos($path, '://') > 0;
        if ($looksLikeFullUri) {
            return $path;
        }

        if (!str_starts_with($path, '/')) {
            return '/' . $path;
        }

        return $path;
    }
}
