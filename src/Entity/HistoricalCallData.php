<?php

namespace App\Entity;

use App\Repository\HistoricalCallDataRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Represents historical call data for a specific time period
 * Used for demand forecasting and workforce planning
 */
#[ORM\Entity(repositoryClass: HistoricalCallDataRepository::class)]
#[ORM\Table(name: 'historical_call_data')]
#[ORM\Index(columns: ['datetime'], name: 'idx_datetime')]
#[ORM\Index(columns: ['queue_id', 'datetime'], name: 'idx_queue_datetime')]
#[ORM\HasLifecycleCallbacks]
class HistoricalCallData
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    /**
     * Reference to the queue this data belongs to
     */
    #[ORM\ManyToOne(targetEntity: Queue::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Queue $queue;

    /**
     * DateTime of the recorded period (e.g., 2025-12-01 09:00:00)
     */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $datetime;

    /**
     * Total number of calls received in this period
     */
    #[ORM\Column(type: Types::INTEGER)]
    private int $callCount = 0;

    /**
     * Average handle time in seconds
     */
    #[ORM\Column(type: Types::FLOAT)]
    private float $averageHandleTimeSeconds = 0.0;

    /**
     * Timestamp when this record was created
     */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    /**
     * Timestamp when this record was last updated
     */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function preUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getQueue(): Queue
    {
        return $this->queue;
    }

    public function setQueue(Queue $queue): self
    {
        $this->queue = $queue;
        return $this;
    }

    public function getDatetime(): \DateTimeImmutable
    {
        return $this->datetime;
    }

    public function setDatetime(\DateTimeImmutable $datetime): self
    {
        $this->datetime = $datetime;
        return $this;
    }

    public function getCallCount(): int
    {
        return $this->callCount;
    }

    public function setCallCount(int $callCount): self
    {
        $this->callCount = $callCount;
        return $this;
    }

    public function getAverageHandleTimeSeconds(): float
    {
        return $this->averageHandleTimeSeconds;
    }

    public function setAverageHandleTimeSeconds(float $averageHandleTimeSeconds): self
    {
        $this->averageHandleTimeSeconds = $averageHandleTimeSeconds;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
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

    /**
     * Convert to HistoricalCallDataDTO for forecasting
     */
    public function toDTO(): \App\Forecasting\HistoricalCallDataDTO
    {
        return new \App\Forecasting\HistoricalCallDataDTO(
            queueName: $this->queue->getName(),
            datetime: $this->datetime,
            callCount: $this->callCount,
            averageHandleTimeSeconds: $this->averageHandleTimeSeconds
        );
    }
}
