<?php

declare(strict_types=1);

namespace Netlogix\EventSourcing\TestEssentials\Queue;

use Flowpack\JobQueue\Common\Exception;
use Flowpack\JobQueue\Common\Job\JobInterface;
use Flowpack\JobQueue\Common\Queue\Message;
use Flowpack\JobQueue\Common\Queue\QueueInterface;
use Neos\Flow\Utility\Algorithms;

class SameProcessFakeQueue implements QueueInterface
{

    protected string $name;

    /**
     * @param string $name
     * @param array $options
     */
    public function __construct(string $name, array $options = [])
    {
        $this->name = $name;
    }

    /**
     * @inheritdoc
     */
    public function setUp(): void
    {
    }

    /**
     * @inheritdoc
     */
    public function getName(): string
    {
        return $this->name;
    }

    public function submit($payload, array $options = []): string
    {
        $messageId = Algorithms::generateUUID();
        $message = new Message($messageId, $payload);
        $job = unserialize($payload);
        if (!$job instanceof JobInterface) {
            throw new \InvalidArgumentException('Given payload could not be unserialized to Job!', 1673545995);
        }

        $result = $job->execute($this, $message);
        if (!$result) {
            throw new Exception(sprintf('Result for Job "%s" was FALSE!', $job->getLabel()), 1673546050);
        }

        return $messageId;
    }

    /**
     * @inheritdoc
     */
    public function waitAndTake(int $timeout = null): Message
    {
        throw new \BadMethodCallException('The FakeQueue does not support reserving of messages.' . chr(10) . 'It is not required to use a worker for this queue as messages are handled immediately upon submission.', 1673546224);
    }

    /**
     * @inheritdoc
     */
    public function waitAndReserve(int $timeout = null): Message
    {
        throw new \BadMethodCallException('The FakeQueue does not support reserving of messages.' . chr(10) . 'It is not required to use a worker for this queue as messages are handled immediately upon submission.', 1673546228);
    }

    /**
     * @inheritdoc
     */
    public function release(string $messageId, array $options = []): void
    {
        throw new \BadMethodCallException('The FakeQueue does not support releasing of failed messages.' . chr(10) . 'The "maximumNumberOfReleases" setting should be removed or set to 0 for this queue!', 1673546231);
    }

    /**
     * @inheritdoc
     */
    public function abort(string $messageId): void
    {
        // The FakeQueue does not support message abortion
    }

    /**
     * @inheritdoc
     */
    public function finish(string $messageId): bool
    {
        // The FakeQueue does not support message finishing
        return false;
    }

    /**
     * @inheritdoc
     */
    public function peek(int $limit = 1): array
    {
        return [];
    }

    /**
     * @inheritdoc
     */
    public function countReady(): int
    {
        return 0;
    }

    /**
     * @inheritdoc
     */
    public function countReserved(): int
    {
        return 0;
    }

    /**
     * @inheritdoc
     */
    public function countFailed(): int
    {
        return 0;
    }

    /**
     * @inheritdoc
     */
    public function flush(): void
    {
        // The FakeQueue does not support message flushing
    }

}
