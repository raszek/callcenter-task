<?php

namespace App\Scheduling;

/**
 * Represents agent's skill and efficiency on a specific queue
 */
readonly class AgentSkillDTO
{
    public function __construct(
        public int $agentId,
        public string $queueName,
        public float $efficiencyCoefficient,
        public int $skillLevel, // 1=capable, 2=proficient, 3=expert
        public bool $isPrimary = false
    ) {
    }
}
