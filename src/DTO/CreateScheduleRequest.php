<?php

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

readonly class CreateScheduleRequest
{
    /**
     * @param string[] $queueNames
     * @param array<string, mixed> $constraints
     */
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\DateTime]
        public string $scheduleStartDate,

        #[Assert\NotBlank]
        #[Assert\DateTime]
        public string $scheduleEndDate,

        #[Assert\NotBlank]
        #[Assert\Type('array')]
        #[Assert\Count(min: 1)]
        public array $queueNames,

        #[Assert\Positive]
        public int $timeSlotGranularityMinutes = 30,

        #[Assert\Positive]
        public int $lookbackWeeks = 4,

        #[Assert\Range(min: 0, max: 1)]
        public float $shrinkageFactor = 0.25,

        #[Assert\Range(min: 0, max: 1)]
        public float $targetOccupancy = 0.85,

        #[Assert\Type('array')]
        public array $constraints = []
    ) {
    }
}
