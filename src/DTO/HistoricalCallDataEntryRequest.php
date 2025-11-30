<?php

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

readonly class HistoricalCallDataEntryRequest
{
    public function __construct(
        #[Assert\NotBlank]
        public string $datetime,

        #[Assert\NotNull]
        #[Assert\Type('integer')]
        #[Assert\GreaterThanOrEqual(0)]
        public int $callCount,

        #[Assert\NotNull]
        #[Assert\Type('float')]
        #[Assert\GreaterThanOrEqual(0)]
        public float $averageHandleTimeSeconds
    ) {
    }
}
