<?php

namespace App\Scheduling;

/**
 * Represents forecasted demand for a queue at a specific time slot
 */
class DemandForecastDTO
{
    public function __construct(
        private string $queueName,
        private \DateTimeImmutable $startTime,
        private \DateTimeImmutable $endTime,
        private int $forecastedCalls,
        private float $requiredFTE, // Full-Time Equivalent hours needed
        private float $confidenceLower, // Pessimistic scenario (-15%)
        private float $confidenceUpper  // Optimistic scenario (+15%)
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

    public function getForecastedCalls(): int
    {
        return $this->forecastedCalls;
    }

    public function getRequiredFTE(): float
    {
        return $this->requiredFTE;
    }

    public function getConfidenceLower(): float
    {
        return $this->confidenceLower;
    }

    public function getConfidenceUpper(): float
    {
        return $this->confidenceUpper;
    }
}
