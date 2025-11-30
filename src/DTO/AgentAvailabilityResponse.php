<?php

namespace App\DTO;

use App\Entity\AgentAvailability;

readonly class AgentAvailabilityResponse
{
    public function __construct(
        public int $id,
        public int $agentId,
        public string $agentFirstName,
        public string $agentLastName,
        public string $startTime,
        public string $endTime,
        public bool $isAvailable
    ) {
    }

    public static function fromEntity(AgentAvailability $agentAvailability): self
    {
        return new self(
            id: $agentAvailability->getId(),
            agentId: $agentAvailability->getAgent()->getId(),
            agentFirstName: $agentAvailability->getAgent()->getFirstName(),
            agentLastName: $agentAvailability->getAgent()->getLastName(),
            startTime: $agentAvailability->getStartTime()->format('Y-m-d H:i:s'),
            endTime: $agentAvailability->getEndTime()->format('Y-m-d H:i:s'),
            isAvailable: $agentAvailability->isAvailable()
        );
    }
}
