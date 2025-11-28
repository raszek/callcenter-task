<?php

namespace App\Forecasting;

/**
 * Represents historical call data for a specific time slot
 */
readonly class HistoricalCallDataDTO
{
    public function __construct(
        public string $queueName,
        public \DateTimeImmutable $datetime,
        public int $callCount,
        public float $averageHandleTimeSeconds
    ) {
    }

    /**
     * Get day of week (1=Monday, 7=Sunday)
     */
    public function getDayOfWeek(): int
    {
        return (int) $this->datetime->format('N');
    }

    /**
     * Get hour of day (0-23)
     */
    public function getHourOfDay(): int
    {
        return (int) $this->datetime->format('H');
    }

    /**
     * Get minute of hour (0-59)
     */
    public function getMinuteOfHour(): int
    {
        return (int) $this->datetime->format('i');
    }
}
