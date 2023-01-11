<?php

declare(strict_types=1);

namespace Netlogix\EventSourcing\TestEssentials\EventStore;

use Neos\EventSourcing\Event\DomainEventInterface;
use Neos\EventSourcing\Event\DomainEvents;
use Neos\EventSourcing\EventStore\EventEnvelope;
use Neos\EventSourcing\EventStore\EventStore;
use Neos\EventSourcing\EventStore\EventStoreFactory;
use Neos\EventSourcing\EventStore\StreamName;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\ObjectManagement\ObjectManagerInterface;
use Netlogix\EventSourcing\ValueObject\EventStoreIdentifier;

/**
 * Helper class for testing command handlers and projectors
 *
 * @Flow\Proxy(false)
 */
class EventStoreBuilder
{

    public static function buildEventStoreWithEvents(
        ObjectManagerInterface $objectManager,
        EventStoreIdentifier $eventStore,
        StreamName $streamName,
        DomainEventInterface ...$events
    ): EventStore {
        $eventStore = self::setupEventStore($objectManager, $eventStore);
        $eventStream = DomainEvents::fromArray($events);
        $eventStore->commit($streamName, $eventStream);
        static::invokeDeferredEventPublishers($objectManager);

        return $eventStore;
    }

    public static function setupEventStore(
        ObjectManagerInterface $objectManager,
        EventStoreIdentifier $eventStore
    ): EventStore {
        $eventStore = self::getEventStoreFromFactory($objectManager, $eventStore);
        $eventStore->setup();

        return $eventStore;
    }

    /**
     * @param ObjectManagerInterface $objectManager
     * @param EventStoreIdentifier $eventStore
     * @return EventStore
     */
    private static function getEventStoreFromFactory(
        ObjectManagerInterface $objectManager,
        EventStoreIdentifier $eventStore
    ): EventStore {
        return $objectManager->get(EventStoreFactory::class)->create((string)$eventStore);
    }

    private static function invokeDeferredEventPublishers(ObjectManagerInterface $objectManager): void
    {
        $objectManager->get(TestingEventPublisherFactory::class)->invokeDeferredEventPublishers();
    }

    /**
     * @param ObjectManagerInterface $objectManager
     * @param EventStoreIdentifier $eventStore
     * @param StreamName $streamName
     * @param int $minimumSequenceNumber
     * @return array<int, DomainEventInterface>
     */
    public static function loadEventStreamAsArrayFromEventStore(
        ObjectManagerInterface $objectManager,
        EventStoreIdentifier $eventStore,
        StreamName $streamName,
        int $minimumSequenceNumber = 0
    ): array {
        $eventStore = self::setupEventStore($objectManager, $eventStore);
        $eventStream = $eventStore->load($streamName, $minimumSequenceNumber);

        return array_map(static function (EventEnvelope $event) {
            return $event->getDomainEvent();
        }, iterator_to_array($eventStream, false));
    }

}
