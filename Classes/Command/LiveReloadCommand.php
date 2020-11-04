<?php
declare(strict_types=1);

namespace Cundd\Assetic\Command;

use Cundd\Assetic\FileWatcher\FileCategories;
use Cundd\Assetic\Server\LiveReload;
use Cundd\CunddComposer\Autoloader;
use Ratchet\Http\HttpServer;
use Ratchet\Server\IoServer;
use Ratchet\WebSocket\WsServer;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use function array_merge;
use function explode;
use function in_array;
use function pathinfo;
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
                'fileadmin'
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
        $fileWatcher = $this->getFileWatcher();
        $fileWatcher->setWatchPaths($this->prepareWatchPaths($path));
        $fileWatcher->setFindFilesMaxDepth($maxDepth);
        $fileWatcher->setInterval($interval);
        if ($suffixes) {
            $fileWatcher->setAssetSuffixes(explode(',', $suffixes));
        }
        $this->printWatchedPaths($output);

        // Websocket server
        $this->liveReloadServer = new LiveReload($notificationDelay);
        $server = IoServer::factory(
            new HttpServer(
                new WsServer(
                    $this->liveReloadServer
                )
            ),
            $port,
            $address
        );

        $server->loop->addPeriodicTimer($interval, [$this, 'recompileIfNeededAndInformLiveReloadServer']);
        $this->liveReloadServer->setEventLoop($server->loop);
        $output->writeln(
            "<info>Websocket server listening on $address:$port running under PHP version " . PHP_VERSION . "</info>"
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
            $this->liveReloadServer->fileDidChange($changedFile);
        }
    }
}
