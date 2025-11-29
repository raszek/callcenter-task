<?php

namespace App\DTO;

use App\Entity\Queue;

readonly class QueueResponse
{
    public function __construct(
        public int $id,
        public string $name,
        public string $displayName
    ) {
    }

    public static function fromEntity(Queue $queue): self
    {
        return new self(
            id: $queue->getId(),
            name: $queue->getName(),
            displayName: $queue->getDisplayName()
        );
    }
}
