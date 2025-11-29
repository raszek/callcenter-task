<?php

namespace App\DTO;

use App\Entity\HistoricalCallData;

readonly class HistoricalCallDataResponse
{
    public function __construct(
        public int $id,
        public int $queueId,
        public string $queueName,
        public string $queueDisplayName,
        public string $datetime,
        public int $callCount,
        public float $averageHandleTimeSeconds
    ) {
    }

    public static function fromEntity(HistoricalCallData $data): self
    {
        return new self(
            id: $data->getId(),
            queueId: $data->getQueue()->getId(),
            queueName: $data->getQueue()->getName(),
            queueDisplayName: $data->getQueue()->getDisplayName(),
            datetime: $data->getDatetime()->format('Y-m-d H:i:s'),
            callCount: $data->getCallCount(),
            averageHandleTimeSeconds: $data->getAverageHandleTimeSeconds()
        );
    }
}
