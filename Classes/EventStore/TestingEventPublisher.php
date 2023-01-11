<?php
declare(strict_types=1);

namespace Netlogix\EventSourcing\TestEssentials\EventStore;

use Flowpack\JobQueue\Common\Job\JobManager;
use Neos\Cache\Frontend\VariableFrontend;
use Neos\EventSourcing\Event\DecoratedEvent;
use Neos\EventSourcing\Event\DomainEventInterface;
use Neos\EventSourcing\Event\DomainEvents;
use Neos\EventSourcing\EventListener\EventListenerInterface;
use Neos\EventSourcing\EventListener\Mapping\EventToListenerMappings;
use Neos\EventSourcing\EventPublisher\EventPublisherInterface;
use Neos\EventSourcing\EventPublisher\JobQueue\CatchUpEventListenerJob;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Cache\CacheManager;
use Neos\Flow\Core\Bootstrap;
use RuntimeException;
use function get_class;

/**
 * An Event Publisher that uses a Job Queue from the Flowpack.JobQueue package to notify Event Listeners of new Events.
 *
 * To only publish catchups for specific Listeners, you can use withAllowedListeners():
 *
 * TestingEventPublisher::withAllowedListeners(
 *   function () use ($event) {
 *       EventStoreBuilder::buildEventStoreWithEvents(
 *           $this->objectManager,
 *           EventStoreIdentifier::fromString('Some.Event:Store'),
 *           StreamName::fromString('foo'),
 *           $event
 *       );
 *   },
 *   MyFavoriteListener::class
 *   );
 */
final class TestingEventPublisher implements EventPublisherInterface
{
    /**
     * @const string
     */
    private const DEFAULT_QUEUE_NAME = 'neos-eventsourcing';

    /**
     * List of allowed listenerClassNames that will be notified of new events.
     * If null, all listeners are allowed.
     *
     * @var array|null
     */
    private static $allowedListenerClassNames = null;

    /**
     * Whether or not the $allowedListenerClassNames need to be loaded from the Cache.
     * This will be true for the very first instance of this class (e.g. whenever a new subprocess is spawned) only.
     *
     * @var bool
     */
    private static $allowedListenersInitialized = false;

    /**
     * @Flow\Inject
     * @var JobManager
     */
    protected $jobManager;

    /**
     * @var string
     */
    private $eventStoreIdentifier;

    /**
     * @var EventToListenerMappings
     */
    private $mappings;

    public function __construct(string $eventStoreIdentifier, EventToListenerMappings $mappings)
    {
        $this->eventStoreIdentifier = $eventStoreIdentifier;
        $this->mappings = $mappings;
    }

    public function initializeObject(): void
    {
        self::loadAllowedListenersFromCache();
    }

    public function publish(DomainEvents $events): void
    {
        if ($events->isEmpty()) {
            return;
        }

        $this->publishEvents($events);
    }

    private function publishEvents(DomainEvents $events): void
    {
        $processedEventClassNames = [];
        $queuedEventListenerClassNames = [];

        foreach ($events as $event) {
            $eventClassName = self::getEventClassName($event);
            // only process every Event type once
            if (array_key_exists($eventClassName, $processedEventClassNames)) {
                continue;
            }
            $processedEventClassNames[$eventClassName] = true;

            foreach ($this->mappings as $mapping) {
                if ($mapping->getEventClassName() !== $eventClassName) {
                    continue;
                }
                if (self::$allowedListenerClassNames !== null
                    && !array_key_exists($mapping->getListenerClassName(), self::$allowedListenerClassNames)) {
                    continue;
                }
                // only process every Event Listener once
                if (array_key_exists($mapping->getListenerClassName(), $queuedEventListenerClassNames)) {
                    continue;
                }

                $job = new CatchUpEventListenerJob($mapping->getListenerClassName(), $this->eventStoreIdentifier);
                // FIXME: queueName should only be determined by mapping in the future
                $queueName = $mapping->getOption('queueName', self::DEFAULT_QUEUE_NAME);
                $options = $mapping->getOption('queueOptions', []);
                $this->jobManager->queue($queueName, $job, $options);
                $queuedEventListenerClassNames[$mapping->getListenerClassName()] = true;
            }
        }
    }

    private static function getEventClassName(DomainEventInterface $event): string
    {
        return get_class($event instanceof DecoratedEvent ? $event->getWrappedEvent() : $event);
    }

    /**
     * @template T
     * @param callable(mixed ... $arguments): T $do
     * @param string ...$listenerClassNames
     * @return T
     */
    public static function withAllowedListeners(callable $do, string ...$listenerClassNames)
    {
        $previousListenerClassNames = self::$allowedListenerClassNames;
        \array_walk($listenerClassNames, function(string $listenerClassName) {
            if (!\is_a($listenerClassName, EventListenerInterface::class, true)) {
                throw new RuntimeException(
                    sprintf('Class %s is not an event listener.', $listenerClassName),
                    1632755291
                );
            }
        });
        self::$allowedListenerClassNames = array_fill_keys($listenerClassNames, 0);
        self::saveAllowedListenersToCache();
        try {
            return $do();
        } finally {
            self::$allowedListenerClassNames = $previousListenerClassNames;
            self::saveAllowedListenersToCache();
        }
    }

    /**
     * @template T
     * @param callable(mixed ... $arguments): T $do
     * @return T
     */
    public static function withoutAnyListeners(callable $do)
    {
        return self::withAllowedListeners($do);
    }

    private static function saveAllowedListenersToCache(): void
    {
        self::$allowedListenersInitialized = true;
        self::getCache()->set('allowed-listeners', self::$allowedListenerClassNames);
    }

    private static function loadAllowedListenersFromCache(): void
    {
        if (self::$allowedListenersInitialized) {
            return;
        }
        $fromCache = self::getCache()->get('allowed-listeners');
        self::$allowedListenerClassNames = $fromCache !== false ? $fromCache : null;
        self::$allowedListenersInitialized = true;
    }

    private static function getCache(): VariableFrontend
    {
        $cacheManager = Bootstrap::$staticObjectManager->get(CacheManager::class);
        $cache = $cacheManager->getCache('Netlogix_EventSourcing_TestEssentials_AllowedListeners');
        assert($cache instanceof VariableFrontend);

        return $cache;
    }

}
