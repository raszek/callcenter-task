<?php

namespace App\DTO;

use App\Entity\AgentSkill;

readonly class AgentSkillResponse
{
    public function __construct(
        public int $id,
        public int $agentId,
        public string $agentFirstName,
        public string $agentLastName,
        public int $queueId,
        public string $queueName,
        public string $queueDisplayName,
        public float $efficiencyCoefficient,
        public int $skillLevel,
        public bool $isPrimary
    ) {
    }

    public static function fromEntity(AgentSkill $agentSkill): self
    {
        return new self(
            id: $agentSkill->getId(),
            agentId: $agentSkill->getAgent()->getId(),
            agentFirstName: $agentSkill->getAgent()->getFirstName(),
            agentLastName: $agentSkill->getAgent()->getLastName(),
            queueId: $agentSkill->getQueue()->getId(),
            queueName: $agentSkill->getQueue()->getName(),
            queueDisplayName: $agentSkill->getQueue()->getDisplayName(),
            efficiencyCoefficient: $agentSkill->getEfficiencyCoefficient(),
            skillLevel: $agentSkill->getSkillLevel(),
            isPrimary: $agentSkill->isPrimary()
        );
    }
}
