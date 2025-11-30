<?php

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

readonly class CreateHistoricalCallDataRequest
{
    /**
     * @param HistoricalCallDataEntryRequest[] $entries
     */
    public function __construct(
        #[Assert\NotBlank]
        public string $queueName,

        #[Assert\NotNull]
        #[Assert\Count(min: 1)]
        #[Assert\Valid]
        public array $entries
    ) {
    }
}
