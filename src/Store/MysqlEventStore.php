<?php

declare(strict_types=1);

namespace App\Store;

use App\DTO\EventDataDTO;
use App\Store\Contract\EventStoreInterface;

final class MysqlEventStore implements EventStoreInterface
{
    public function __construct(
        private readonly \PDO $connection,
    ) {}

    public function store(array $events): void
    {
        //implement store method
    }

    public function getLastEventId(string $sourceName): int
    {
        $stmt = $this->connection->prepare(
            'SELECT MAX(source_event_id) FROM events WHERE source_name = :source'
        );
        $stmt->execute(['source' => $sourceName]);

        return (int) $stmt->fetchColumn();
    }
}
