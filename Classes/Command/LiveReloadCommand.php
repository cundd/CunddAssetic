<?php
declare(strict_types=1);

namespace Cundd\Assetic\Command;

use Cundd\Assetic\FileWatcher\FileCategories;
use Cundd\Assetic\Server\LiveReload;
use Cundd\CunddComposer\Autoloader;
use InvalidArgumentException;
use LogicException;
use Ratchet\Http\HttpServer;
use Ratchet\Server\IoServer;
use Ratchet\WebSocket\WsServer;
use React\EventLoop\Factory;
use React\Socket\SecureServer;
use React\Socket\Server;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use function array_merge;
use function class_exists;
use function explode;
use function file_exists;
use function in_array;
use function is_readable;
use function pathinfo;
use function rtrim;
use function sprintf;
use function substr;
use const PATHINFO_EXTENSION;
use const PHP_VERSION;

/**
 * Command to start the LiveReload server
 */
class LiveReloadCommand extends AbstractCommand implements ColorInterface
{
    /**
     * @var LiveReload
     */
    private $liveReloadServer;

    protected function configure()
    {
        $this
            ->setDescription('Start a LiveReload server')
            ->addOption(
                'address',
                'A',
                InputOption::VALUE_REQUIRED,
                'IP to listen',
                '0.0.0.0'
            )
            ->addOption(
                'port',
                'P',
                InputOption::VALUE_REQUIRED,
                'Port to listen',
                35729
            )
            ->addOption(
                'interval',
                'i',
                InputOption::VALUE_REQUIRED,
                'Interval between checks',
                .5
            )
            ->addOption(
                'path',
                'p',
                InputOption::VALUE_REQUIRED,
                'Directory path(s) that should be watched (separated by comma ",")',
                'fileadmin,EXT:client'
            )
            ->addOption(
                'suffixes',
                's',
                InputOption::VALUE_REQUIRED,
                'File suffixes to watch for changes (separated by comma ",")',
                ''
            )
            ->addOption(
                'max-depth',
                'd',
                InputOption::VALUE_REQUIRED,
                'Maximum directory depth of file to watch',
                7
            )
            ->addOption(
                'notification-delay',
                'o',
                InputOption::VALUE_REQUIRED,
                'Number of seconds to wait before sending the reload command to the clients',
                0.0
            )
            ->addOption(
                'tls-certificate',
                null,
                InputOption::VALUE_REQUIRED,
                'Path to the TLS certificate in a PEM file (php.net/manual/context.ssl.php#context.ssl.local-cert)'
            )
            ->addOption(
                'tls-private-key',
                null,
                InputOption::VALUE_REQUIRED,
                'Path to the private key file (php.net/manual/context.ssl.php#context.ssl.local-pk)'
            );
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        Autoloader::register();

        $address = $input->getOption('address');
        $port = $input->getOption('port');
        $path = $input->getOption('path');
        $suffixes = $input->getOption('suffixes');
        $maxDepth = (int)$input->getOption('max-depth');
        $notificationDelay = (float)$input->getOption('notification-delay');

        $interval = max((float)$input->getOption('interval'), .5);
        $this->buildFileWatcher($path, $maxDepth, $suffixes);
        $this->printWatchedPaths($output);

        $useTLS = (bool)$input->getOption('tls-certificate');
        $server = $this->buildServer($input, $address, $port, $notificationDelay, $useTLS, $interval);
        $prefix = $useTLS ? 'Secure ' : '';
        $output->writeln(
            "<info>{$prefix}Websocket server listening on $address:$port running under PHP version " . PHP_VERSION . "</info>"
        );

        $server->run();

        return 0;
    }

    /**
     * Re-compiles the sources if needed and additionally informs the LiveReload server about the changes
     */
    public function recompileIfNeededAndInformLiveReloadServer()
    {
        $fileNeedsRecompile = $this->needsRecompile();
        if (!$fileNeedsRecompile) {
            return;
        }

        $needFullPageReload = in_array(
            pathinfo($fileNeedsRecompile, PATHINFO_EXTENSION),
            array_merge(FileCategories::$scriptAssetSuffixes, FileCategories::$otherAssetSuffixes)
        );
        if ($needFullPageReload) {
            $this->liveReloadServer->fileDidChange($fileNeedsRecompile, false);
        } else {
            $changedFile = $this->compile(true);
            $this->liveReloadServer->fileDidChange($changedFile, true);
        }
    }

    /**
     * @param InputInterface $input
     * @return array
     */
    private function buildSecureServerContext(InputInterface $input): array
    {
        return [
            'local_cert'        => $this->assertTlsFilePath($input, 'tls-certificate'),
            'local_pk'          => $this->assertTlsFilePath($input, 'tls-private-key'),
            'allow_self_signed' => true,
            'verify_peer'       => false,
        ];
    }

    private function assertTlsFilePath(InputInterface $input, string $optionName): string
    {
        $path = $input->getOption($optionName);
        if (!$path) {
            throw new InvalidArgumentException(sprintf('Option "%s" is not given', $optionName));
        }

        $homeDirectory = $this->getHomeDirectory();
        if (substr($path, 0, 2) === '~/' && $homeDirectory) {
            $path = rtrim($homeDirectory, '/') . '/' . substr($path, 2);
        }

        if (is_readable($path)) {
            return $path;
        }

        if (file_exists($path)) {
            throw new InvalidArgumentException(
                sprintf('File "%s" for configuration %s exists, but is not readable', $path, $optionName)
            );
        } else {
            throw new InvalidArgumentException(
                sprintf('File "%s" for configuration %s does not exist', $path, $optionName)
            );
        }
    }

    private function getHomeDirectory(): string
    {
        return $_SERVER['HOME'] ?? '';
    }

    /**
     * @param InputInterface $input
     * @param string         $address
     * @param int|string     $port
     * @param float          $notificationDelay
     * @param bool           $useTLS
     * @param int|float      $interval The number of seconds to wait before execution.
     * @return IoServer
     */
    private function buildServer(
        InputInterface $input,
        string $address,
        $port,
        float $notificationDelay,
        bool $useTLS,
        $interval
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
            $loop = Factory::create();

            $server = new SecureServer(
                new Server($address . ':' . $port, $loop),
                $loop,
                $this->buildSecureServerContext($input)
            );

            $server = new IoServer($component, $server, $loop);
        }

        $server->loop->addPeriodicTimer($interval, [$this, 'recompileIfNeededAndInformLiveReloadServer']);
        $this->liveReloadServer->setEventLoop($server->loop);

        return $server;
    }

    /**
     * @param     $path
     * @param int $maxDepth
     * @param     $suffixes
     */
    private function buildFileWatcher($path, int $maxDepth, $suffixes): void
    {
        $fileWatcher = $this->getFileWatcher();
        $fileWatcher->setWatchPaths($this->prepareWatchPaths($path));
        $fileWatcher->setFindFilesMaxDepth($maxDepth);
        if ($suffixes) {
            $fileWatcher->setAssetSuffixes(explode(',', $suffixes));
        }
    }
}
