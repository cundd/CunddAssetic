<?php

declare(strict_types=1);

namespace Cundd\Assetic\Tests\Unit\BuildSystem;

use Assetic\Contracts\Asset\AssetInterface;
use Assetic\Contracts\Filter\FilterInterface;
use Cundd\Assetic\BuildSystem\Executor;
use Cundd\Assetic\Compiler\AssetCollector;
use Cundd\Assetic\Compiler\CompilerFactory;
use Cundd\Assetic\Configuration;
use Cundd\Assetic\Configuration\LiveReloadConfiguration;
use Cundd\Assetic\Configuration\StylesheetConfiguration;
use Cundd\Assetic\Output\OutputFileFinder;
use Cundd\Assetic\Output\OutputFileHashService;
use Cundd\Assetic\Output\OutputFileService;
use Cundd\Assetic\Output\SymlinkService;
use Cundd\Assetic\ValueObject\BuildState;
use Cundd\Assetic\ValueObject\FinalOutputFilePath;
use Cundd\Assetic\ValueObject\SymlinkFilePath;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Site\Entity\Site;

final class ExecutorTest extends TestCase
{
    public const TEST_CONTENT = '/* would have compiled the scss file */';

    public function testBuild(): void
    {
        $result = $this->buildExecutor()->build($this->buildConfiguration(
            createSymlink: false,
            stylesheetConfigurations: [
                $this->buildTestStylesheetConfiguration(),
            ]
        ));

        $this->assertTrue($result->isOk());
        /** @var BuildState $buildState */
        $buildState = $result->unwrap();

        $filePath = $buildState->getFilePath();
        $this->assertInstanceOf(
            FinalOutputFilePath::class,
            $filePath
        );

        $this->assertSame(
            self::TEST_CONTENT,
            file_get_contents($filePath->getAbsoluteUri())
        );
    }

    public function testBuildWithSymlink(): void
    {
        $result = $this->buildExecutor()->build($this->buildConfiguration(
            createSymlink: true,
            stylesheetConfigurations: [
                $this->buildTestStylesheetConfiguration(),
            ]
        ));

        $this->assertTrue($result->isOk());
        /** @var BuildState $buildState */
        $buildState = $result->unwrap();

        $filePath = $buildState->getFilePath();
        $this->assertTrue($filePath->isSymlink());
        $this->assertInstanceOf(
            SymlinkFilePath::class,
            $filePath
        );

        $this->assertSame(
            self::TEST_CONTENT,
            file_get_contents($filePath->getAbsoluteUri())
        );
    }

    /**
     * @param StylesheetConfiguration[] $stylesheetConfigurations
     */
    private function buildConfiguration(
        array $stylesheetConfigurations,
        bool $createSymlink,
    ): Configuration {
        $site = new Site('test-site', 1, []);

        $filter = new class implements FilterInterface {
            public function filterLoad(AssetInterface $asset): void
            {
                $asset->setContent(ExecutorTest::TEST_CONTENT);
            }

            public function filterDump(AssetInterface $asset): void
            {
            }
        };

        return new Configuration(
            site: $site,
            stylesheetConfigurations: $stylesheetConfigurations,
            outputFileDir: sys_get_temp_dir(), // @phpstan-ignore argument.type
            outputFileName: null,
            filterForType: ['scss' => get_class($filter)],
            filterBinaries: [],
            liveReloadConfiguration: new LiveReloadConfiguration(false, 0, true),
            isDevelopment: false, // is not relevant
            createSymlink: $createSymlink,
            allowDeveloperFeaturesWithoutLogin: false,
            strictModeEnabled: true,
        );
    }

    private function buildExecutor(): Executor
    {
        $compilerFactory = new CompilerFactory(new AssetCollector());

        return new Executor(
            $compilerFactory,
            new OutputFileHashService(),
            new OutputFileService(),
            new SymlinkService(),
            new OutputFileFinder()
        );
    }

    private function buildTestStylesheetConfiguration(): StylesheetConfiguration
    {
        return new StylesheetConfiguration(
            __DIR__ . '/../../Resources/test.scss',
            [],// $stylesheet['functions'],
            [],// $stylesheet['developmentFunctions'],
            null, // $stylesheet['type'],
        );
    }
}
