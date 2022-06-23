<?php
declare(strict_types=1);

namespace Cundd\Assetic\Service;

use Cundd\Assetic\ValueObject\FilePath;
use Cundd\Assetic\ValueObject\PathWoHash;

interface OutputFileServiceInterface
{
    /**
     * Return the current output filename without the hash
     *
     * If an output file name is set in the configuration use it, otherwise create it by combining the file names of the
     * assets.
     *
     * @return PathWoHash
     */
    public function getPathWoHash(): PathWoHash;

    /**
     * Return the expected final File Path
     *
     * @param PathWoHash $outputFilenameWithoutHash
     * @return FilePath|null
     */
    public function getExpectedPathWithHash(PathWoHash $outputFilenameWithoutHash): ?FilePath;
}
