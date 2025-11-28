<?php

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

readonly class CreateAgentSkillRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Positive]
        public int $queueId,

        #[Assert\NotBlank]
        #[Assert\Positive]
        public float $efficiencyCoefficient,

        #[Assert\NotBlank]
        #[Assert\Positive]
        public int $skillLevel,

        #[Assert\NotNull]
        public bool $isPrimary
    ) {
    }
}
