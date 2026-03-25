<?php

declare(strict_types=1);

namespace App\Store\Contract;

use App\DTO\EventDataDTO;

interface EventStoreInterface
{
    /**
     * Persist a batch of events.
     * If this method returns without exception, data is considered reliably stored.
     *
     * @param EventDataDTO[] $events
     */
    public function store(array $events): void;

    /**
     * Get the last stored event ID for a given source.
     * Returns 0 if no events have been stored yet.
     */
    public function getLastEventId(string $sourceName): int;
}
