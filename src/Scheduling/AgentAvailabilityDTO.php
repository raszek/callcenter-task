<?php

namespace App\Scheduling;

/**
 * Represents agent's declared availability for a specific time slot
 */
readonly class AgentAvailabilityDTO
{
    public function __construct(
        public int $agentId,
        public \DateTimeImmutable $startTime,
        public \DateTimeImmutable $endTime,
        public bool $isAvailable
    ) {
    }
}
