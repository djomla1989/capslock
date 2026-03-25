<?php

declare(strict_types=1);

namespace App\Source;

use App\DTO\EventDataDTO;
use App\Source\Contract\EventSourceInterface;
use App\Source\Exception\SourceUnavailableException;

final class FtpCsvEventSource implements EventSourceInterface
{
    public function __construct(
        private readonly string $name
    ) {}

    public function getName(): string
    {
        return $this->name;
    }

    public function fetchEvents(int $lastEventId, int $limit = 1000): array
    {
        try {
            //throw exception to test
            throw new \Exception("You shall not...");
            return [$this->mapToDTO()];
        } catch (\Throwable $e) {
            throw new SourceUnavailableException(
                sprintf('FTP source "%s" unavailable: %s', $this->name, $e->getMessage()),
                previous: $e
            );
        }
    }

    /**
     * @return EventDataDTO
     */
    private function mapToDTO(): EventDataDTO
    {
        return new EventDataDTO(
            id: (int) (microtime(true) * 1000000),
            sourceName: $this->name,
            type: 'ftp',
            payload: [],
            occurredAt: new \DateTimeImmutable('now'),
        );
    }
}
