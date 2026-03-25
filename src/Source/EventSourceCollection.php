<?php

declare(strict_types=1);

namespace App\Source;

use App\Source\Contract\EventSourceInterface;

/**
 * @implements \IteratorAggregate<int, EventSourceInterface>
 */
final class EventSourceCollection implements \IteratorAggregate, \Countable
{
    /** @var EventSourceInterface[] */
    private array $sources;

    public function __construct(EventSourceInterface ...$sources)
    {
        $this->sources = $sources;
    }

    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->sources);
    }

    public function count(): int
    {
        return count($this->sources);
    }
}
