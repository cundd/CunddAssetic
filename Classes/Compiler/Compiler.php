<?php

declare(strict_types=1);

namespace Cundd\Assetic\Compiler;

use Assetic\AssetManager;
use Assetic\AssetWriter;
use Cundd\Assetic\Configuration;
use Cundd\Assetic\Utility\ProfilingUtility;
use Cundd\Assetic\ValueObject\PathWithoutHash;
use Cundd\Assetic\ValueObject\Result;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Throwable;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Compiler
 *
 * The class that builds the connection between Assetic and TYPO3
 */
final class Compiler implements CompilerInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;

    public function __construct(private readonly AssetCollector $assetCollector)
    {
    }

    public function compile(
        Configuration $configuration,
        PathWithoutHash $outputPath,
    ): Result {
        $outputDirectory = Environment::getPublicPath()
            . '/' . $configuration->outputFileDir;
        GeneralUtility::mkdir($outputDirectory);

        $assetCollection = $this->assetCollector->collectAssets($configuration);
        $assetCollection->setTargetPath($outputPath->getFileName());

        $assetManager = new AssetManager();
        $assetManager->set('cundd_assetic', $assetCollection);
        $writer = new AssetWriter($outputDirectory);

        ProfilingUtility::start('Will write assets');
        try {
            $writer->writeManagerAssets($assetManager);
        } catch (Throwable $exception) {
            return new Result\Err($exception);
        }

        ProfilingUtility::end('Did write assets');

        return new Result\Ok(null);
    }
}
