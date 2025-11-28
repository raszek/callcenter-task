<?php

namespace App\Scheduling;

/**
 * Represents forecasted demand for a queue at a specific time slot
 */
readonly class DemandForecastDTO
{
    public function __construct(
        public string $queueName,
        public \DateTimeImmutable $startTime,
        public \DateTimeImmutable $endTime,
        public int $forecastedCalls,
        public float $requiredFTE, // Full-Time Equivalent hours needed
        public float $confidenceLower, // Pessimistic scenario (-15%)
        public float $confidenceUpper  // Optimistic scenario (+15%)
    ) {
    }
}
