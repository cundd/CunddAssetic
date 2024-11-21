<?php

declare(strict_types=1);

namespace Cundd\Assetic\ValueObject;

class BuildState
{
    private FilePath $filePath;

    private PathWoHash $outputFilePathWithoutHash;

    private array $filesToCleanUp;

    /**
     * @param string[] $filesToCleanUp
     */
    public function __construct(
        FilePath $filePath,
        PathWoHash $outputFilePathWithoutHash,
        array $filesToCleanUp,
    ) {
        $this->filePath = $filePath;
        $this->filesToCleanUp = $filesToCleanUp;
        $this->outputFilePathWithoutHash = $outputFilePathWithoutHash;
    }

    public function getFilePath(): FilePath
    {
        return $this->filePath;
    }

    public function withFilePath(FilePath $filePath): self
    {
        $clone = clone $this;
        $clone->filePath = $filePath;

        return $clone;
    }

    public function getOutputFilePathWithoutHash(): PathWoHash
    {
        return $this->outputFilePathWithoutHash;
    }

    /**
     * @return string[]
     */
    public function getFilesToCleanUp(): array
    {
        return $this->filesToCleanUp;
    }

    /**
     * @param string[] $filesToCleanUp
     */
    public function withFilesToCleanUp(array $filesToCleanUp): self
    {
        $clone = clone $this;
        $clone->filesToCleanUp = $filesToCleanUp;

        return $clone;
    }
}
