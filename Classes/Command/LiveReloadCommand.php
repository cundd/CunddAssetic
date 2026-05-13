<?php

declare(strict_types=1);

namespace Cundd\Assetic\Command;

use Cundd\Assetic\Configuration;
use Cundd\Assetic\Configuration\LiveReloadConfiguration;
use Cundd\Assetic\FileWatcher\FileCategories;
use Cundd\Assetic\Server\IoServer;
use Cundd\Assetic\Server\IoServerFactory;
use Cundd\Assetic\Server\LiveReloadComponent;
use Cundd\Assetic\Utility\PathUtility;
use Cundd\Assetic\ValueObject\CompilationContext;
use InvalidArgumentException;
use LogicException;
use Ratchet\Http\HttpServer;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;
use TYPO3\CMS\Core\Utility\MathUtility;
use UnexpectedValueException;

use function array_merge;
use function class_exists;
use function file_exists;
use function in_array;
use function is_readable;
use function pathinfo;
use function rtrim;
use function sprintf;
use function substr;

use const PATHINFO_EXTENSION;
use const PHP_EOL;
use const PHP_VERSION;

/**
 * Command to start the LiveReload server
 *
 * @phpstan-import-type SecureServerContext from IoServerFactory
 */
class LiveReloadCommand extends AbstractWatchCommand
{
    private const OPTION_ADDRESS = 'address';
    private const OPTION_PORT = 'port';
    private const OPTION_NOTIFICATION_DELAY = 'notification-delay';
    private const OPTION_TLS_CERTIFICATE = 'tls-certificate';
    private const OPTION_TLS_PRIVATE_KEY = 'tls-private-key';

    private bool $lastCompilationFailed = false;

    private ConsoleLogger $logger;

    protected function configure(): void
    {
        $this->setDescription('Start a LiveReload server');
        $this->setHelp(sprintf(
            'To use encrypted communication with the LiveReload server (e.g. if your site is served with "https") a valid certificate must be provided in a PEM file.
If the PEM file includes the private key, the single option `--%s` can be used. Otherwise the private key file must be specified with `--%s`.

For additional information on the expected files see the following links:
`--%s`: https://www.php.net/manual/context.ssl.php#context.ssl.local-cert
`--%s`: https://www.php.net/manual/en/context.ssl.php#context.ssl.local-pk
',
            self::OPTION_TLS_CERTIFICATE,
            self::OPTION_TLS_PRIVATE_KEY,
            self::OPTION_TLS_CERTIFICATE,
            self::OPTION_TLS_PRIVATE_KEY
        ));
        $this->registerDefaultArgumentsAndOptions();
        $this
            ->addOption(
                self::OPTION_ADDRESS,
                'A',
                InputOption::VALUE_REQUIRED,
                'IP to listen',
                '0.0.0.0'
            )
            ->addOption(
                self::OPTION_PORT,
                'P',
                InputOption::VALUE_REQUIRED,
                'Port to listen',
                35729
            )
            ->addOption(
                self::OPTION_NOTIFICATION_DELAY,
                'o',
                InputOption::VALUE_REQUIRED,
                'Number of seconds to wait before sending the reload command to the clients',
                0.0
            )
            ->addOption(
                self::OPTION_TLS_CERTIFICATE,
                't',
                InputOption::VALUE_REQUIRED,
                'Path to the TLS certificate in a PEM file'
            )
            ->addOption(
                self::OPTION_TLS_PRIVATE_KEY,
                'k',
                InputOption::VALUE_REQUIRED,
                'Path to the private key file'
            );
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!class_exists(HttpServer::class)) {
            throw new LogicException(
                'The Ratchet classes could not be found' . PHP_EOL
                    . 'See README.md#livereload for configuration instructions',
                1356543545
            );
        }

        $compilationContext = $this->getCompilationContext($input);
        $configuration = $this->getConfigurationWithPort(
            $compilationContext,
            $input,
        );
        if (false === $configuration->liveReloadConfiguration->isEnabled) {
            $io = new SymfonyStyle($input, $output);
            $io->warning('LiveReload is not enabled in site settings');
        } elseif (false === $configuration->createSymlink) {
            $io = new SymfonyStyle($input, $output);
            $io->warning(
                'Creation of required symlinks is not enabled in site settings' . PHP_EOL
                    . 'LiveReload will not work properly'
            );
        }
        $this->logger = new ConsoleLogger($output);
        $fileWatcher = $this->getFileWatcher();
        $this->configureFileWatcherFromInput($input, $output, $fileWatcher);

        $server = $this->buildServerFromInput(
            $configuration,
            $compilationContext,
            $input,
            $output
        );

        $server->run();

        return 0;
    }

    /**
     * Re-compiles the sources if needed and additionally informs the LiveReload
     * server component about the changes
     */
    public function recompileIfNeededAndInformLiveReloadServer(
        Configuration $configuration,
        CompilationContext $compilationContext,
        LiveReloadComponent $liveReloadServerComponent,
    ): void {
        $fileNeedsRecompile = $this->needsRecompile($this->getFileWatcher());
        if (!$fileNeedsRecompile) {
            $this->logger->debug('No files changed');

            return;
        }

        $this->logger->info(
            sprintf('File {file} did change'),
            ['file' => $fileNeedsRecompile]
        );

        $needFullPageReload = $this->needsFullPageReload($fileNeedsRecompile);
        if ($needFullPageReload) {
            $liveReloadServerComponent->fileDidChange($fileNeedsRecompile, false);
        } else {
            $result = $this->compile($configuration, $compilationContext);
            if ($result->isErr()) {
                /** @var Throwable $error */
                $error = $result->unwrapErr();
                $this->logger->error($error->getMessage(), ['error' => $error]);
                $liveReloadServerComponent->fileDidChange('', false);
            } else {
                $compiledFile = $result->unwrap()->getPublicUri();
                if (!$this->lastCompilationFailed) {
                    $liveReloadServerComponent->fileDidChange($compiledFile, true);
                } else {
                    $liveReloadServerComponent->fileDidChange('', false);
                }
            }

            $this->lastCompilationFailed = $result->isErr();
        }
    }

    /**
     * @return SecureServerContext
     */
    private function buildSecureServerContext(InputInterface $input): array
    {
        $context = [
            'local_cert' => $this->getTLSFilePath(
                $input,
                self::OPTION_TLS_CERTIFICATE,
                false
            ),
            'allow_self_signed' => true,
            'verify_peer'       => false,
        ];

        $privateKey = $this->getTLSFilePath(
            $input,
            self::OPTION_TLS_PRIVATE_KEY,
            true
        );
        if ($privateKey) {
            $context['local_pk'] = $privateKey;
        }

        return $context;
    }

    private function getTLSFilePath(
        InputInterface $input,
        string $optionName,
        bool $optional,
    ): string {
        $path = $input->getOption($optionName);
        if (!is_string($path) || '' === $path) {
            if ($optional) {
                return '';
            }
            throw new InvalidArgumentException(
                sprintf('Option "%s" is not given', $optionName),
                7653682383
            );
        }

        $homeDirectory = PathUtility::getHomeDirectory();
        if ('~/' === substr($path, 0, 2) && $homeDirectory) {
            $path = rtrim($homeDirectory, '/') . '/' . substr($path, 2);
        }

        if (is_readable($path)) {
            return $path;
        }

        if (file_exists($path)) {
            throw new InvalidArgumentException(
                sprintf(
                    'File "%s" for configuration %s exists, but is not readable',
                    $path,
                    $optionName
                ),
                1909396619
            );
        } else {
            throw new InvalidArgumentException(
                sprintf(
                    'File "%s" for configuration %s does not exist',
                    $path,
                    $optionName
                ),
                9185054796
            );
        }
    }

    private function buildServerFromInput(
        Configuration $configuration,
        CompilationContext $compilationContext,
        InputInterface $input,
        OutputInterface $output,
    ): IoServer {
        $address = trim((string) $input->getOption(self::OPTION_ADDRESS));
        if (!$address) {
            throw new InvalidArgumentException(
                'Argument "address" must not be empty'
            );
        }

        $port = $configuration->liveReloadConfiguration->port;
        $notificationDelay = (float) $input->getOption(
            self::OPTION_NOTIFICATION_DELAY
        );
        $periodicInterval = $this->getInterval($input, 0.5);

        $liveReloadServerComponent = new LiveReloadComponent($notificationDelay);

        $useTLS = (bool) $input->getOption(self::OPTION_TLS_CERTIFICATE);
        $secureServerContext = $useTLS
            ? $this->buildSecureServerContext($input)
            : null;
        $server = (new IoServerFactory())->buildServer(
            $configuration,
            $compilationContext,
            $liveReloadServerComponent,
            $address,
            $port,
            $notificationDelay,
            $secureServerContext,
            $periodicInterval,
            fn () => $this->recompileIfNeededAndInformLiveReloadServer(
                $configuration,
                $compilationContext,
                $liveReloadServerComponent,
            ),
        );
        $prefix = $useTLS ? 'Secure ' : '';
        $output->writeln(
            "<info>{$prefix}Websocket server listening on $address:$port running under PHP version " . PHP_VERSION . '</info>'
        );

        return $server;
    }

    private function needsFullPageReload(string $fileNeedsRecompile): bool
    {
        return in_array(
            pathinfo($fileNeedsRecompile, PATHINFO_EXTENSION),
            array_merge(
                FileCategories::$scriptAssetSuffixes,
                FileCategories::$otherAssetSuffixes
            )
        );
    }

    private function getConfigurationWithPort(
        CompilationContext $compilationContext,
        InputInterface $input,
    ): Configuration {
        $configuration = $this->getConfiguration($compilationContext);

        // Check if the "port" option was given, when building the `Configuration`
        $port = $input->getOption(LiveReloadCommand::OPTION_PORT);
        if (!is_numeric($port) || !MathUtility::canBeInterpretedAsInteger($port) || (int) $port < 0) {
            throw new UnexpectedValueException('Invalid port option given');
        }
        $liveReloadConfiguration = new LiveReloadConfiguration(
            isEnabled: $configuration->liveReloadConfiguration->isEnabled,
            skipServerTest: $configuration->liveReloadConfiguration->skipServerTest,
            port: (int) $port,
        );

        return new Configuration(
            site: $configuration->site,
            stylesheetConfigurations: $configuration->stylesheetConfigurations,
            outputFileDir: $configuration->outputFileDir,
            outputFileName: $configuration->outputFileName,
            filterForType: $configuration->filterForType,
            filterBinaries: $configuration->filterBinaries,
            liveReloadConfiguration: $liveReloadConfiguration,
            isDevelopment: $configuration->isDevelopment,
            createSymlink: $configuration->createSymlink,
            allowDeveloperFeaturesWithoutLogin: $configuration->allowDeveloperFeaturesWithoutLogin,
            strictModeEnabled: $configuration->strictModeEnabled,
        );
    }
}
