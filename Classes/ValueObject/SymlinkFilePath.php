<?php

declare(strict_types=1);

namespace Cundd\Assetic\ValueObject;

use Cundd\Assetic\Exception\FilePathException;
use TYPO3\CMS\Core\Core\Environment;

final class SymlinkFilePath extends FilePath
{
    public function isSymlink(): bool
    {
        assert(parent::isSymlink());

        return true;
    }

    public function readlink(): FilePath
    {
        assert(parent::isSymlink());

        $target = readlink($this->getAbsoluteUri());
        if (false === $target) {
            throw new FilePathException(sprintf(
                'Could not resolve symbolic link "%s"',
                $this->getAbsoluteUri()
            ));
        }

        $publicPath = Environment::getPublicPath();
        if (!str_starts_with($target, $publicPath)) {
            throw new FilePathException(sprintf(
                'Symlinked resolved to file outside of public path %s',
                $publicPath
            ));
        }

        return new FilePath($target);
    }
}
