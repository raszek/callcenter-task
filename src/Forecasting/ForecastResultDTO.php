<?php

namespace App\Forecasting;

/**
 * Result DTO containing forecast calculations
 */
readonly class ForecastResultDTO
{
    public function __construct(
        public string $queueName,
        public \DateTimeImmutable $startTime,
        public \DateTimeImmutable $endTime,
        public float $forecastedCalls,
        public float $averageHandleTimeSeconds,
        public float $requiredFTE,
        public float $confidenceLowerFTE,
        public float $confidenceUpperFTE,
        public int $dataPointsUsed,
        public float $standardDeviation,
        public array $metadata = []
    ) {
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
