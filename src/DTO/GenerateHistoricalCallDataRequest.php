<?php

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

readonly class GenerateHistoricalCallDataRequest
{
    public function __construct(
        #[Assert\Range(min: 1, max: 365)]
        public int $days = 30,

        #[Assert\Range(min: 1, max: 24)]
        public int $intervalHours = 1
    ) {
    }
}
