<?php

declare(strict_types=1);

namespace Netlogix\EventSourcing\TestEssentials\Common;

trait FindInstancesOfTrait
{
    public function findFirstInstanceOf(string $class, iterable $subject)
    {
        return current(
            $this->findInstancesOf($class, $subject)
        ) ?: null;
    }

    public function findInstancesOf(string $class, iterable $subject): array
    {
        return iterator_to_array((function (string $class, iterable $subject) {
            foreach ($subject as $item) {
                if (is_object($item) && is_a($item, $class, true)) {
                    yield $item;
                }
            }
        })($class, $subject));
    }
}
