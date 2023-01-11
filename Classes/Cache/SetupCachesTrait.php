<?php

declare(strict_types=1);

namespace Netlogix\EventSourcing\TestEssentials\Cache;

use Neos\Cache\Backend\PdoBackend;
use Neos\Cache\Backend\WithSetupInterface;
use Neos\Cache\Exception\NoSuchCacheException;
use Neos\Flow\Cache\CacheManager;
use Neos\Flow\ObjectManagement\ObjectManagerInterface;
use Neos\Utility\ObjectAccess;

/**
 * @property-read ObjectManagerInterface $objectManager
 */
trait SetupCachesTrait
{

    /**
     * Run setup for all Caches
     *
     * @see \Neos\Flow\Command\CacheCommandController::setupAllCommand
     * @throws NoSuchCacheException
     */
    protected function setupCaches(): void
    {
        $cacheManager = $this->objectManager->get(CacheManager::class);

        $cacheConfigurations = $cacheManager->getCacheConfigurations();
        unset($cacheConfigurations['Default']);

        foreach ($cacheConfigurations as $cacheIdentifier => $configuration) {
            $backendClassName = $configuration['backend'] ?? '';

            if (is_a($backendClassName, WithSetupInterface::class, true)) {
                $cache = $cacheManager->getCache($cacheIdentifier);
                $cacheBackend = $cache->getBackend();
                assert($cacheBackend instanceof WithSetupInterface);
                $result = $cacheBackend->setup();

                if ($result->hasErrors()) {
                    throw new \RuntimeException('Could not setup cache ' . $cacheIdentifier . ': ' . $result->getFirstError()->getMessage(), 1593612825);
                }
            }

            if (is_a($backendClassName, PdoBackend::class, true)) {
                $cache = $cacheManager->getCache($cacheIdentifier);
                $cacheBackend = $cache->getBackend();

                ObjectAccess::setProperty(
                    $cacheBackend,
                    'cacheEntriesIterator',
                    null,
                    true
                );
            }
        }
    }
}
