<?php

declare(strict_types=1);

namespace Cundd\Assetic\ValueObject;

use Cundd\Assetic\Configuration\ConfigurationProviderInterface;
use Cundd\Assetic\Utility\ConfigurationUtility;

use function rtrim;

use const DIRECTORY_SEPARATOR;

class FilePath
{
    private string $fileName;

    private string $relativeDirectoryPath;

    private string $absoluteDirectoryPath;

    final public function __construct(string $fileName, string $relativeDirectoryPath, string $absoluteDirectoryPath)
    {
        $this->fileName = $fileName;
        $this->relativeDirectoryPath = rtrim($relativeDirectoryPath, DIRECTORY_SEPARATOR);
        $this->absoluteDirectoryPath = rtrim($absoluteDirectoryPath, DIRECTORY_SEPARATOR);
    }

    /**
     * @return static
     */
    public static function fromFileName(
        string $fileName,
        ConfigurationProviderInterface $configurationProvider,
    ): self {
        return new static(
            ConfigurationUtility::getDomainIdentifier() . $fileName,
            $configurationProvider->getOutputFileDir(),
            $configurationProvider->getAbsoluteOutputFileDir()
        );
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
        return $this->absoluteDirectoryPath . '/' . $this->fileName;
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
