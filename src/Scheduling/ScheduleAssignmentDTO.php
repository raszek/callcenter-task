<?php

namespace App\Scheduling;

/**
 * Represents a single schedule assignment for an agent
 */
class ScheduleAssignmentDTO
{
    public function __construct(
        private int $agentId,
        private string $queueName,
        private \DateTimeImmutable $startTime,
        private \DateTimeImmutable $endTime,
        private float $efficiencyScore,
        private string $assignmentType = 'primary' // primary, secondary, flexible
    ) {
    }

    public function getAgentId(): int
    {
        return $this->agentId;
    }

    public function getQueueName(): string
    {
        return $this->queueName;
    }

    public function getStartTime(): \DateTimeImmutable
    {
        return $this->startTime;
    }

    public function getEndTime(): \DateTimeImmutable
    {
        return $this->endTime;
    }

    public function getEfficiencyScore(): float
    {
        return $this->efficiencyScore;
    }

    public function getAssignmentType(): string
    {
        return $this->assignmentType;
    }

    public function getDurationInHours(): float
    {
        $interval = $this->startTime->diff($this->endTime);
        return $interval->h + ($interval->i / 60);
    }
}
