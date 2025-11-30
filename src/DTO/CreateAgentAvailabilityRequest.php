<?php

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

readonly class CreateAgentAvailabilityRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\DateTime]
        public string $startTime,

        #[Assert\NotBlank]
        #[Assert\DateTime]
        public string $endTime,

        #[Assert\NotNull]
        public bool $isAvailable
    ) {
    }
}
