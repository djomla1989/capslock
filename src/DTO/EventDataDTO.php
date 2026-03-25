<?php

declare(strict_types=1);

namespace App\DTO;

final class EventDataDTO
{
    public function __construct(
        public readonly int $id,
        public readonly string $sourceName,
        public readonly string $type,
        public readonly array $payload,
        public readonly \DateTimeImmutable $occurredAt,
    ) {}
}
