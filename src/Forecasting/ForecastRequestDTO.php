<?php

namespace App\Forecasting;

/**
 * Request DTO for forecast calculation
 */
readonly class ForecastRequestDTO
{
    /**
     * @param HistoricalCallDataDTO[] $historicalData
     */
    public function __construct(
        public string $queueName,
        public \DateTimeImmutable $targetDatetime,
        public array $historicalData,
        public int $timeGranularityMinutes = 30,
        public int $lookbackWeeks = 4,
        public float $targetServiceLevel = 0.80,
        public int $targetAnswerTimeSeconds = 20,
        public float $shrinkageFactor = 0.25,
        public float $targetOccupancy = 0.85,
        public float $confidenceIntervalPercentage = 0.15
    ) {
    }

    /**
     * Get the end time of the target time slot
     */
    public function getTargetEndDatetime(): \DateTimeImmutable
    {
        return $this->targetDatetime->add(
            new \DateInterval('PT' . $this->timeGranularityMinutes . 'M')
        );
    }
}
