<?php

declare(strict_types=1);

namespace Cundd\Assetic\Command;

use Cundd\Assetic\FileWatcher\FileCategories;
use Cundd\Assetic\Server\LiveReload;
use InvalidArgumentException;
use LogicException;
use Ratchet\Http\HttpServer;
use Ratchet\Server\IoServer;
use Ratchet\WebSocket\WsServer;
use React\EventLoop\Loop;
use React\Socket\SecureServer;
use React\Socket\SocketServer;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

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
 */
class LiveReloadCommand extends AbstractWatchCommand
{
    private const OPTION_ADDRESS = 'address';
    private const OPTION_PORT = 'port';
    private const OPTION_NOTIFICATION_DELAY = 'notification-delay';
    private const OPTION_TLS_CERTIFICATE = 'tls-certificate';
    private const OPTION_TLS_PRIVATE_KEY = 'tls-private-key';

    private LiveReload $liveReloadServer;

    private bool $lastCompilationFailed = false;

    private ConsoleLogger $logger;

    protected function configure()
    {
        $this->setDescription('Start a LiveReload server');
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
                null,
                InputOption::VALUE_REQUIRED,
                'Path to the TLS certificate in a PEM file (php.net/manual/context.ssl.php#context.ssl.local-cert)'
            )
            ->addOption(
                self::OPTION_TLS_PRIVATE_KEY,
                null,
                InputOption::VALUE_REQUIRED,
                'Path to the private key file (php.net/manual/context.ssl.php#context.ssl.local-pk)'
            );
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        if (false === $this->getConfigurationProvider()->getCreateSymlink()) {
            $io = new SymfonyStyle($input, $output);
            $io->warning(
                'Creation of required symlinks is not yet configured in TypoScript' . PHP_EOL . 'LiveReload may not work properly'
            );
        }
        $this->logger = new ConsoleLogger($output);
        $fileWatcher = $this->getFileWatcher();
        $this->configureFileWatcherFromInput($input, $output, $fileWatcher);

        $server = $this->buildServerFromInput($input, $output);

        $server->run();

        return 0;
    }

    /**
     * Re-compiles the sources if needed and additionally informs the LiveReload server about the changes
     */
    public function recompileIfNeededAndInformLiveReloadServer(): void
    {
        $fileNeedsRecompile = $this->needsRecompile($this->getFileWatcher());
        if (!$fileNeedsRecompile) {
            $this->logger->debug('No files changed');

            return;
        }

        $this->logger->info(sprintf('File {file} did change'), ['file' => $fileNeedsRecompile]);

        $needFullPageReload = $this->needsFullPageReload($fileNeedsRecompile);
        if ($needFullPageReload) {
            $this->liveReloadServer->fileDidChange($fileNeedsRecompile, false);
        } else {
            $result = $this->compile();
            if ($result->isErr()) {
                /** @var Throwable $error */
                $error = $result->unwrapErr();
                $this->logger->error($error->getMessage(), ['error' => $error]);
                $this->liveReloadServer->fileDidChange('', false);
            } else {
                $compiledFile = $result->unwrap()->getPublicUri();
                if (!$this->lastCompilationFailed) {
                    $this->liveReloadServer->fileDidChange($compiledFile, true);
                } else {
                    $this->liveReloadServer->fileDidChange('', false);
                }
            }

            $this->lastCompilationFailed = $result->isErr();
        }
    }

    /**
     * @return array{local_cert: string, local_pk: string, allow_self_signed: true, verify_peer: false}
     */
    private function buildSecureServerContext(InputInterface $input): array
    {
        return [
            'local_cert'        => $this->assertTlsFilePath($input, self::OPTION_TLS_CERTIFICATE),
            'local_pk'          => $this->assertTlsFilePath($input, self::OPTION_TLS_PRIVATE_KEY),
            'allow_self_signed' => true,
            'verify_peer'       => false,
        ];
    }

    private function assertTlsFilePath(InputInterface $input, string $optionName): string
    {
        $path = $input->getOption($optionName);
        if (!$path) {
            throw new InvalidArgumentException(sprintf('Option "%s" is not given', $optionName), 7653682383);
        }

        $homeDirectory = $this->getHomeDirectory();
        if ('~/' === substr($path, 0, 2) && $homeDirectory) {
            $path = rtrim($homeDirectory, '/') . '/' . substr($path, 2);
        }

        if (is_readable($path)) {
            return $path;
        }

        if (file_exists($path)) {
            throw new InvalidArgumentException(sprintf('File "%s" for configuration %s exists, but is not readable', $path, $optionName), 1909396619);
        } else {
            throw new InvalidArgumentException(sprintf('File "%s" for configuration %s does not exist', $path, $optionName), 9185054796);
        }
    }

    private function getHomeDirectory(): string
    {
        return $_SERVER['HOME'] ?? '';
    }

    private function buildServerFromInput(InputInterface $input, OutputInterface $output): IoServer
    {
        $address = $input->getOption(self::OPTION_ADDRESS);
        $port = $input->getOption(self::OPTION_PORT);
        $notificationDelay = (float) $input->getOption(self::OPTION_NOTIFICATION_DELAY);
        $interval = $this->getInterval($input, 0.5);

        $useTLS = (bool) $input->getOption(self::OPTION_TLS_CERTIFICATE);
        $server = $this->buildServer($input, $address, $port, $notificationDelay, $useTLS, $interval);
        $prefix = $useTLS ? 'Secure ' : '';
        $output->writeln(
            "<info>{$prefix}Websocket server listening on $address:$port running under PHP version " . PHP_VERSION . '</info>'
        );

        return $server;
    }

    /**
     * @param int|string $port
     * @param int|float  $interval the number of seconds to wait before execution
     */
    private function buildServer(
        InputInterface $input,
        string $address,
        $port,
        float $notificationDelay,
        bool $useTLS,
        $interval,
    ): IoServer {
        if (!class_exists(HttpServer::class)) {
            throw new LogicException('The Ratchet classes could not be found', 1356543545);
        }

        // LiveReload server
        $this->liveReloadServer = new LiveReload($notificationDelay);

        $component = new HttpServer(
            new WsServer(
                $this->liveReloadServer
            )
        );

        if (!$useTLS) {
            $server = IoServer::factory(
                $component,
                $port,
                $address
            );
        } else {
            $context = $this->buildSecureServerContext($input);
            $loop = Loop::get();

            $server = new SecureServer(
                new SocketServer($address . ':' . $port),
                $loop,
                $context
            );

            $server = new IoServer($component, $server, $loop);
        }

        $server->loop->addPeriodicTimer($interval, [$this, 'recompileIfNeededAndInformLiveReloadServer']);
        $this->liveReloadServer->setEventLoop($server->loop);

        return $server;
    }

    private function needsFullPageReload(string $fileNeedsRecompile): bool
    {
        return in_array(
            pathinfo($fileNeedsRecompile, PATHINFO_EXTENSION),
            array_merge(FileCategories::$scriptAssetSuffixes, FileCategories::$otherAssetSuffixes)
        );
    }
}
