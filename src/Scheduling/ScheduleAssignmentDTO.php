<?php

namespace App\Scheduling;

/**
 * Represents a single schedule assignment for an agent
 */
readonly class ScheduleAssignmentDTO
{
    public function __construct(
        public int $agentId,
        public string $queueName,
        public \DateTimeImmutable $startTime,
        public \DateTimeImmutable $endTime,
        public float $efficiencyScore,
        public string $assignmentType = 'primary' // primary, secondary, flexible
    ) {
    }

    public function getDurationInHours(): float
    {
        $interval = $this->startTime->diff($this->endTime);
        return $interval->h + ($interval->i / 60);
    }
}
