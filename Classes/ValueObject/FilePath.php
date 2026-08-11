<?php

declare(strict_types=1);

namespace Cundd\Assetic\ValueObject;

use Cundd\Assetic\Configuration;
use Cundd\Assetic\Exception\FilePathException;
use TYPO3\CMS\Core\Core\Environment;

use function rtrim;

class FilePath
{
    /**
     * @param non-empty-string $path
     */
    final public function __construct(private readonly string $path)
    {
        $fileName = basename($path);
        if (!str_starts_with($path, '/')) {
            throw new FilePathException(sprintf(
                'Path must be absolute "%s" given',
                $path
            ));
        }
        $fileName = basename($path);
        if (empty($fileName)) {
            throw new FilePathException(sprintf(
                'Missing file name in path "%s"',
                $path
            ));
        }

        $directory = dirname($path);
        if (empty($directory)) {
            throw new FilePathException(sprintf(
                'Missing directory in path "%s"',
                $path
            ));
        }
    }

    /**
     * @param non-empty-string $fileName
     */
    public static function fromFileName(
        string $fileName,
        Configuration $configuration,
    ): static {
        return new static(
            rtrim($configuration->outputFileDir, '/') . '/' . $fileName
        );
    }

    /**
     * Return the public web-URI
     *
     * @return non-empty-string
     */
    public function getPublicUri(): string
    {
        $publicPath = Environment::getPublicPath();
        if (!str_starts_with($this->path, $publicPath)) {
            throw new FilePathException(sprintf(
                'File appears to be outside of public path %s',
                $publicPath
            ));
        }

        $relativePath = substr($this->path, strlen($publicPath));
        if (empty($relativePath)) {
            throw new FilePathException(sprintf(
                'Could not detect relative path for %s',
                $this->path
            ));
        }

        return $relativePath;
    }

    /**
     * Return the absolute file-system URI
     *
     * @return non-empty-string
     */
    public function getAbsoluteUri(): string
    {
        return $this->path;
    }

    public function getFileName(): string
    {
        return basename($this->path);
    }

    public function isSymlink(): bool
    {
        return is_link($this->getAbsoluteUri());
    }

    public function isReadable(): bool
    {
        return is_readable($this->getAbsoluteUri());
    }
}
