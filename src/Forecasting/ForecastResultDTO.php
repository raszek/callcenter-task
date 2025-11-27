<?php

namespace App\Forecasting;

/**
 * Result DTO containing forecast calculations
 */
class ForecastResultDTO
{
    public function __construct(
        private string $queueName,
        private \DateTimeImmutable $startTime,
        private \DateTimeImmutable $endTime,
        private float $forecastedCalls,
        private float $averageHandleTimeSeconds,
        private float $requiredFTE,
        private float $confidenceLowerFTE,
        private float $confidenceUpperFTE,
        private int $dataPointsUsed,
        private float $standardDeviation,
        private array $metadata = []
    ) {
    }

    public function getQueueName(): string
    {
        return $this->queueName;
    }

    public function getStartTime(): \DateTimeImmutable
    {
        return $this->startTime;
    }

    public function getEndTime(): \DateTimeImmutable
    {
        return $this->endTime;
    }

    public function getForecastedCalls(): float
    {
        return $this->forecastedCalls;
    }

    public function getAverageHandleTimeSeconds(): float
    {
        return $this->averageHandleTimeSeconds;
    }

    public function getRequiredFTE(): float
    {
        return $this->requiredFTE;
    }

    public function getConfidenceLowerFTE(): float
    {
        return $this->confidenceLowerFTE;
    }

    public function getConfidenceUpperFTE(): float
    {
        return $this->confidenceUpperFTE;
    }

    public function getDataPointsUsed(): int
    {
        return $this->dataPointsUsed;
    }

    public function getStandardDeviation(): float
    {
        return $this->standardDeviation;
    }

    public function getMetadata(): array
    {
        return $this->metadata;
    }

    /**
     * Get forecasted calls as integer
     */
    public function getForecastedCallsRounded(): int
    {
        return (int) round($this->forecastedCalls);
    }

    /**
     * Get required agents (ceiling of FTE)
     */
    public function getRequiredAgents(): int
    {
        return (int) ceil($this->requiredFTE);
    }

    /**
     * Get confidence interval width
     */
    public function getConfidenceIntervalWidth(): float
    {
        return $this->confidenceUpperFTE - $this->confidenceLowerFTE;
    }

    /**
     * Convert to DemandForecastDTO (for scheduling integration)
     */
    public function toDemandForecastDTO(): \App\Scheduling\DemandForecastDTO
    {
        return new \App\Scheduling\DemandForecastDTO(
            queueName: $this->queueName,
            startTime: $this->startTime,
            endTime: $this->endTime,
            forecastedCalls: $this->getForecastedCallsRounded(),
            requiredFTE: $this->requiredFTE,
            confidenceLower: $this->confidenceLowerFTE,
            confidenceUpper: $this->confidenceUpperFTE
        );
    }
}
