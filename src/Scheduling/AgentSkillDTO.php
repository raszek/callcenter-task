<?php

namespace App\Scheduling;

/**
 * Represents agent's skill and efficiency on a specific queue
 */
class AgentSkillDTO
{
    public function __construct(
        private int $agentId,
        private string $queueName,
        private float $efficiencyCoefficient,
        private int $skillLevel, // 1=capable, 2=proficient, 3=expert
        private bool $isPrimary = false
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

    public function getEfficiencyCoefficient(): float
    {
        return $this->efficiencyCoefficient;
    }

    public function getSkillLevel(): int
    {
        return $this->skillLevel;
    }

    public function isPrimary(): bool
    {
        return $this->isPrimary;
    }
}
