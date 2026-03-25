<?php

declare(strict_types=1);

namespace App\Source\Contract;

use App\DTO\EventDataDTO;

interface EventSourceInterface
{
    /**
     * Unique identifier for this event source.
     */
    public function getName(): string;

    /**
     * Fetch events with ID greater than $lastEventId, sorted ascending by ID.
     * Returns up to 1000 events per call.
     *
     * @param int $lastEventId
     * @param int $limit
     * @return EventDataDTO[]
     *
     * @throws \App\Source\Exception\SourceUnavailableException
     */
    public function fetchEvents(int $lastEventId, int $limit = 1000): array;
}
