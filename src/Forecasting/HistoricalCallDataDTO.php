<?php

namespace App\Forecasting;

/**
 * Represents historical call data for a specific time slot
 */
class HistoricalCallDataDTO
{
    public function __construct(
        private string $queueName,
        private \DateTimeImmutable $datetime,
        private int $callCount,
        private float $averageHandleTimeSeconds
    ) {
    }

    public function getQueueName(): string
    {
        return $this->queueName;
    }

    public function getDatetime(): \DateTimeImmutable
    {
        return $this->datetime;
    }

    public function getCallCount(): int
    {
        return $this->callCount;
    }

    public function getAverageHandleTimeSeconds(): float
    {
        return $this->averageHandleTimeSeconds;
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
