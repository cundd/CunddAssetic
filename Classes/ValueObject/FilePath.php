<?php

declare(strict_types=1);

namespace Cundd\Assetic\ValueObject;

use Cundd\Assetic\Configuration;
use TYPO3\CMS\Core\Core\Environment;

use function rtrim;

use const DIRECTORY_SEPARATOR;

class FilePath
{
    private string $fileName;

    private string $relativeDirectoryPath;

    final public function __construct(
        string $fileName,
        string $relativeDirectoryPath,
    ) {
        $this->fileName = $fileName;
        $this->relativeDirectoryPath = rtrim($relativeDirectoryPath, DIRECTORY_SEPARATOR);
    }

    public static function fromFileName(
        string $fileName,
        Configuration $configuration,
    ): static {
        return new static($fileName, $configuration->outputFileDir);
    }

    /**
     * Return the public web-URI
     *
     * @return non-empty-string
     */
    public function getPublicUri(): string
    {
        return $this->relativeDirectoryPath . '/' . $this->fileName;
    }

    /**
     * Return the absolute file-system URI
     *
     * @return non-empty-string
     */
    public function getAbsoluteUri(): string
    {
        return Environment::getPublicPath()
            . '/' . $this->relativeDirectoryPath
            . '/' . $this->fileName;
    }

    public function getFileName(): string
    {
        return $this->fileName;
    }

    public function isSymlink(): bool
    {
        return is_link($this->getAbsoluteUri());
    }
}
