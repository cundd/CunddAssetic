<?php
declare(strict_types=1);

namespace Cundd\Assetic\BuildStep;

use Assetic\Exception\FilterException;
use Cundd\Assetic\Compiler\CompilerInterface;
use Cundd\Assetic\Configuration\ConfigurationProviderFactory;
use Cundd\Assetic\Configuration\ConfigurationProviderInterface;
use Cundd\Assetic\Exception\OutputFileException;
use Cundd\Assetic\Utility\ExceptionPrinter;
use Cundd\Assetic\ValueObject\BuildState;
use Cundd\Assetic\ValueObject\BuildStateResult;
use Throwable;
use TYPO3\CMS\Core\Log\LogManager;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use function php_sapi_name;

class Compile implements BuildStepInterface
{
    private CompilerInterface $compiler;

    private ConfigurationProviderInterface $configurationProvider;

    public function __construct(
        CompilerInterface $compiler,
        ConfigurationProviderFactory $configurationProviderFactory
    ) {
        $this->compiler = $compiler;
        $this->configurationProvider = $configurationProviderFactory->build();
    }

    public function process(BuildState $currentState): BuildStateResult
    {
        $result = $this->compiler->compile();

        return $result->isOk()
            ? BuildStateResult::ok($currentState)
            : BuildStateResult::err($result->unwrapErr());
    }

    /**
     * Handle exceptions
     *
     * @param FilterException|OutputFileException $exception
     * @return void
     * @throws Throwable if run in CLI mode
     */
    private function handleCompilerException(Throwable $exception): void
    {
        if ($this->configurationProvider->isDevelopment()) {
            if (php_sapi_name() == 'cli') {
                throw $exception;
            }
            $exceptionPrinter = new ExceptionPrinter();
            echo $exceptionPrinter->printException($exception);
        } else {
            $this->logException($exception);
        }
    }

    private function logException(Throwable $exception)
    {
        $logger = GeneralUtility::makeInstance(LogManager::class)->getLogger(__CLASS__);
        $logger->error('Caught exception #' . $exception->getCode() . ': ' . $exception->getMessage());
    }
}
