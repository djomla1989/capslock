<?php

declare(strict_types=1);

namespace App\Source;

use App\DTO\EventDataDTO;
use App\Source\Contract\EventSourceInterface;
use App\Source\Exception\SourceUnavailableException;

final class SoapEventSource implements EventSourceInterface
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
            return [$this->mapToDTO()];
        } catch (\SoapFault $e) {
            throw new SourceUnavailableException(
                sprintf('SOAP source "%s" unavailable: %s', $this->name, $e->getMessage()),
                previous: $e,
            );
        }
    }

    private function mapToDTO(): EventDataDTO
    {
        return new EventDataDTO(
            id: (int) (microtime(true) * 1000000),
            sourceName: $this->name,
            type: 'soap',
            payload: [],
            occurredAt: new \DateTimeImmutable(),
        );
    }
}
