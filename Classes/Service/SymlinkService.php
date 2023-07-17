<?php

declare(strict_types=1);

namespace Cundd\Assetic\Service;

use Cundd\Assetic\Configuration\ConfigurationProviderFactory;
use Cundd\Assetic\Configuration\ConfigurationProviderInterface;
use Cundd\Assetic\Exception\SymlinkException;
use Cundd\Assetic\ValueObject\FilePath;
use Cundd\Assetic\ValueObject\PathWoHash;

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
     * This defines if the instance is allowed to create a new symlink and was able to delete the old one
     *
     * @var bool
     */
    private bool $isOwnerOfSymlink = false;

    private ConfigurationProviderInterface $configurationProvider;

    public function __construct(ConfigurationProviderFactory $configurationProviderFactory)
    {
        $this->configurationProvider = $configurationProviderFactory->build();
    }

    /**
     * Create the symlink to the given final path
     *
     * @param FilePath   $fileFinalPath
     * @param PathWoHash $outputFilePathWithoutHash
     * @return FilePath|null
     */
    public function createSymlinkToFinalPath(
        FilePath $fileFinalPath,
        PathWoHash $outputFilePathWithoutHash
    ): ?FilePath {
        if (!$this->configurationProvider->getCreateSymlink()) {
            return null;
        }
        $symlinkPath = $this->getSymlinkPath($outputFilePathWithoutHash);
        $symlinkPathString = $symlinkPath->getAbsoluteUri();
        if ($fileFinalPath->getAbsoluteUri() !== $symlinkPathString) {
            clearstatcache(true, $symlinkPathString);
            if ($this->isOwnerOfSymlink || !is_link($symlinkPathString)) {
                if (!is_link($symlinkPathString) && !symlink($fileFinalPath->getAbsoluteUri(), $symlinkPathString)) {
                    throw new SymlinkException(
                        sprintf(
                            'Could not create the symlink "%s" because %s',
                            $symlinkPathString,
                            \Cundd\Assetic\Utility\PathUtility::getReasonForWriteFailure($symlinkPathString)
                        ),
                        1456396454
                    );
                }
            } else {
                throw new SymlinkException(
                    sprintf(
                        'Could not create the symlink because the file "%s" already exists and the manager is not the symlink\'s owner',
                        $symlinkPathString
                    )
                );
            }
        }

        return $symlinkPath;
    }

    /**
     * Remove the symlink
     */
    public function removeSymlink(PathWoHash $outputFilePathWithoutHash)
    {
        if (!$this->configurationProvider->getCreateSymlink()) {
            return;
        }
        // Unlink the symlink
        $symlinkPath = $this->getSymlinkPath($outputFilePathWithoutHash)->getAbsoluteUri();
        if (is_link($symlinkPath)) {
            if (unlink($symlinkPath)) {
                $this->isOwnerOfSymlink = true;
            } else {
                $this->isOwnerOfSymlink = false;
                throw new SymlinkException(
                    sprintf('Could not acquire ownership of symlink "%s"', $symlinkPath)
                );
            }
        } elseif (!file_exists($symlinkPath)) {
            $this->isOwnerOfSymlink = true;
        } else {
            throw new SymlinkException(
                sprintf('Could not acquire ownership of symlink "%s" because it exists but is no link', $symlinkPath)
            );
        }
    }

    /**
     * Return the symlink URI
     *
     * @param PathWoHash $outputFilePathWithoutHash
     * @return FilePath
     */
    public function getSymlinkPath(PathWoHash $outputFilePathWithoutHash): FilePath
    {
        $fileName = '_debug_'
            . $outputFilePathWithoutHash->getFileName()
            . '.css';

        return FilePath::fromFileName($fileName, $this->configurationProvider);
    }
}
