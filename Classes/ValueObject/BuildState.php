<?php

declare(strict_types=1);

namespace Cundd\Assetic\ValueObject;

final class BuildState
{
    /**
     * @param string[] $filesToCleanUp
     */
    public function __construct(
        private readonly FilePath $filePath,
        private readonly PathWoHash $outputFilePathWithoutHash,
        private readonly array $filesToCleanUp,
    ) {
    }

    public function getFilePath(): FilePath
    {
        return $this->filePath;
    }

    public function withFilePath(FilePath $filePath): self
    {
        return new self(
            $filePath,
            $this->outputFilePathWithoutHash,
            $this->filesToCleanUp
        );
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
        return new self(
            $this->filePath,
            $this->outputFilePathWithoutHash,
            $filesToCleanUp
        );
    }
}
