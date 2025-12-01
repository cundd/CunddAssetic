<?php

declare(strict_types=1);

namespace Cundd\Assetic;

use Cundd\Assetic\Configuration\LiveReloadConfiguration;
use TYPO3\CMS\Core\Site\Entity\Site;

/**
 * @phpstan-type StylesheetConfiguration array<string|array<string,mixed>>
 */
final class Configuration
{
    public const OUTPUT_FILE_DIR = 'typo3temp/cundd_assetic/';

    /**
     * @param StylesheetConfiguration     $stylesheetConfigurations
     * @param array<string, class-string> $filterForType
     * @param array<string, string>       $filterBinaries
     */
    public function __construct(
        public readonly Site $site,
        public readonly array $stylesheetConfigurations,
        public readonly string $outputFileDir,
        public readonly ?string $outputFileName,
        public readonly array $filterForType,
        public readonly array $filterBinaries,
        public readonly LiveReloadConfiguration $liveReloadConfiguration,
        public readonly bool $isDevelopment,
        public readonly bool $createSymlink,
        public readonly bool $allowCompileWithoutLogin,
        public readonly bool $strictModeEnabled,
    ) {
    }
}
