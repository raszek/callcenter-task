<?php

namespace App\DTO;

use App\Entity\Agent;

readonly class AgentResponse
{
    public function __construct(
        public int $id,
        public string $firstName,
        public string $lastName
    ) {
    }

    public static function fromEntity(Agent $agent): self
    {
        return new self(
            id: $agent->getId(),
            firstName: $agent->getFirstName(),
            lastName: $agent->getLastName()
        );
    }
}
