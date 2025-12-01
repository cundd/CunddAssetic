<?php

declare(strict_types=1);

namespace Cundd\Assetic\Service;

use Cundd\Assetic\Configuration;
use Cundd\Assetic\Exception\SymlinkException;
use Cundd\Assetic\Utility\PathUtility;
use Cundd\Assetic\ValueObject\FilePath;
use Cundd\Assetic\ValueObject\PathWithoutHash;

use function clearstatcache;
use function file_exists;
use function is_link;
use function sprintf;
use function symlink;
use function unlink;

class SymlinkService implements SymlinkServiceInterface
{
    /**
     * Defines if this instance is the owner of the symlink
     *
     * This defines if the instance is allowed to create a new symlink and was
     * able to delete the old one
     */
    private bool $isOwnerOfSymlink = false;

    /**
     * Create the symlink to the given final path
     */
    public function createSymlinkToFinalPath(
        Configuration $configuration,
        FilePath $fileFinalPath,
        PathWithoutHash $outputFilePathWithoutHash,
    ): ?FilePath {
        if (!$configuration->createSymlink) {
            return null;
        }
        $symlinkPath = $this->getSymlinkPath(
            $configuration,
            $outputFilePathWithoutHash
        );
        $symlinkPathString = $symlinkPath->getAbsoluteUri();
        if ($fileFinalPath->getAbsoluteUri() !== $symlinkPathString) {
            clearstatcache(true, $symlinkPathString);
            if ($this->isOwnerOfSymlink || !is_link($symlinkPathString)) {
                if (!is_link($symlinkPathString) && !symlink($fileFinalPath->getAbsoluteUri(), $symlinkPathString)) {
                    throw new SymlinkException(
                        sprintf(
                            'Could not create the symlink "%s" because %s',
                            $symlinkPathString,
                            PathUtility::getReasonForWriteFailure($symlinkPathString)
                        ),
                        1456396454
                    );
                }
            } else {
                throw new SymlinkException(
                    sprintf(
                        'Could not create the symlink because the file "%s" already exists and the manager is not the symlink\'s owner',
                        $symlinkPathString
                    ),
                    8096081149
                );
            }
        }

        return $symlinkPath;
    }

    /**
     * Remove the symlink
     */
    public function removeSymlink(
        Configuration $configuration,
        PathWithoutHash $outputFilePathWithoutHash,
    ): void {
        if (!$configuration->createSymlink) {
            return;
        }
        // Unlink the symlink
        $symlinkPath = $this->getSymlinkPath(
            $configuration,
            $outputFilePathWithoutHash
        )
            ->getAbsoluteUri();
        if (is_link($symlinkPath)) {
            if (@unlink($symlinkPath)) {
                $this->isOwnerOfSymlink = true;
            } else {
                $this->isOwnerOfSymlink = false;
                throw new SymlinkException(
                    sprintf(
                        'Could not acquire ownership of symlink "%s"',
                        $symlinkPath
                    ),
                    4792841029
                );
            }
        } elseif (!file_exists($symlinkPath)) {
            $this->isOwnerOfSymlink = true;
        } else {
            throw new SymlinkException(
                sprintf(
                    'Could not acquire ownership of symlink "%s" because it exists but is no link',
                    $symlinkPath
                ),
                2237181159
            );
        }
    }

    /**
     * Return the symlink URI
     */
    public function getSymlinkPath(
        Configuration $configuration,
        PathWithoutHash $outputFilePathWithoutHash,
    ): FilePath {
        $fileName = '_debug_'
            . $outputFilePathWithoutHash->getFileName()
            . '.css';

        return FilePath::fromFileName($fileName, $configuration);
    }
}
