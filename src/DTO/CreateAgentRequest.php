<?php

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

readonly class CreateAgentRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 255)]
        public string $firstName,

        #[Assert\NotBlank]
        #[Assert\Length(max: 255)]
        public string $lastName
    ) {
    }
}
