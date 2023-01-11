<?php
declare(strict_types=1);

namespace Netlogix\EventSourcing\TestEssentials\EventStore;

use Neos\EventSourcing\EventListener\Mapping\DefaultEventToListenerMappingProvider;
use Neos\EventSourcing\EventPublisher\DeferEventPublisher;
use Neos\EventSourcing\EventPublisher\EventPublisherFactoryInterface;
use Neos\EventSourcing\EventPublisher\EventPublisherInterface;
use Neos\Flow\Annotations as Flow;

/**
 * A Testing Event Publisher factory that is used to have manual event publishing using
 * a CrossContextEventAwareJobQueueEventPublisher.
 *
 * @Flow\Scope("singleton")
 */
final class TestingEventPublisherFactory implements EventPublisherFactoryInterface
{

    /**
     * @var DefaultEventToListenerMappingProvider
     */
    private $mappingProvider;

    /**
     * A list of all initialized Event Publisher instances, indexed by the "Event Store identifier"
     *
     * @var EventPublisherInterface[]
     */
    private $eventPublisherInstances = [];

    public function __construct(DefaultEventToListenerMappingProvider $mappingProvider)
    {
        $this->mappingProvider = $mappingProvider;
    }

    public function create(string $eventStoreIdentifier): EventPublisherInterface
    {
        if (!isset($this->eventPublisherInstances[$eventStoreIdentifier])) {
            $mappings = $this->mappingProvider->getMappingsForEventStore($eventStoreIdentifier);
            $this->eventPublisherInstances[$eventStoreIdentifier] = DeferEventPublisher::forPublisher(
                new TestingEventPublisher($eventStoreIdentifier, $mappings)
            );
        }
        return $this->eventPublisherInstances[$eventStoreIdentifier];
    }

    public function invokeDeferredEventPublishers(): void
    {
        foreach ((array)$this->eventPublisherInstances as $eventPublisher) {
            if ($eventPublisher instanceof DeferEventPublisher) {
                $eventPublisher->invoke();
            }
        }
    }
}
