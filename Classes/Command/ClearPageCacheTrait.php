<?php

declare(strict_types=1);

namespace Cundd\Assetic\Command;

use Doctrine\DBAL\Exception\ConnectionException;
use Symfony\Component\Console\Output\OutputInterface;
use TYPO3\CMS\Core\Cache\CacheManager as TYPO3CacheManager;
use TYPO3\CMS\Core\Cache\Exception as CacheException;

trait ClearPageCacheTrait
{
    private function tryClearPageCache(TYPO3CacheManager $cacheManager, OutputInterface $output): void
    {
        try {
            $cacheManager->flushCachesInGroup('pages');
        } catch (ConnectionException|CacheException $_) { // @phpstan-ignore catch.neverThrown
            $output->writeln('<comment>Failed to clear the cache</comment>');
        }
    }
}
