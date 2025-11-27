<?php

namespace App\Scheduling;

/**
 * Represents agent's declared availability for a specific time slot
 */
class AgentAvailabilityDTO
{
    public function __construct(
        private int $agentId,
        private \DateTimeImmutable $startTime,
        private \DateTimeImmutable $endTime,
        private bool $isAvailable
    ) {
    }

    public function getAgentId(): int
    {
        return $this->agentId;
    }

    public function getStartTime(): \DateTimeImmutable
    {
        return $this->startTime;
    }

    public function getEndTime(): \DateTimeImmutable
    {
        return $this->endTime;
    }

    public function isAvailable(): bool
    {
        return $this->isAvailable;
    }
}
