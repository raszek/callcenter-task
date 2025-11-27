<?php

namespace App\Forecasting;

/**
 * Request DTO for forecast calculation
 */
class ForecastRequestDTO
{
    /**
     * @param HistoricalCallDataDTO[] $historicalData
     */
    public function __construct(
        private string $queueName,
        private \DateTimeImmutable $targetDatetime,
        private array $historicalData,
        private int $timeGranularityMinutes = 30,
        private int $lookbackWeeks = 4,
        private float $targetServiceLevel = 0.80,
        private int $targetAnswerTimeSeconds = 20,
        private float $shrinkageFactor = 0.25,
        private float $targetOccupancy = 0.85,
        private float $confidenceIntervalPercentage = 0.15
    ) {
    }

    public function getQueueName(): string
    {
        return $this->queueName;
    }

    public function getTargetDatetime(): \DateTimeImmutable
    {
        return $this->targetDatetime;
    }

    /**
     * @return HistoricalCallDataDTO[]
     */
    public function getHistoricalData(): array
    {
        return $this->historicalData;
    }

    public function getTimeGranularityMinutes(): int
    {
        return $this->timeGranularityMinutes;
    }

    public function getLookbackWeeks(): int
    {
        return $this->lookbackWeeks;
    }

    public function getTargetServiceLevel(): float
    {
        return $this->targetServiceLevel;
    }

    public function getTargetAnswerTimeSeconds(): int
    {
        return $this->targetAnswerTimeSeconds;
    }

    public function getShrinkageFactor(): float
    {
        return $this->shrinkageFactor;
    }

    public function getTargetOccupancy(): float
    {
        return $this->targetOccupancy;
    }

    public function getConfidenceIntervalPercentage(): float
    {
        return $this->confidenceIntervalPercentage;
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
