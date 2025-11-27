<?php

namespace App\Entity;

use App\Repository\QueueRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Represents a call center queue (e.g., sales, technical support, complaints)
 */
#[ORM\Entity(repositoryClass: QueueRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Queue
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    /**
     * Unique internal name (e.g., 'sales', 'support', 'complaints')
     */
    #[ORM\Column(type: Types::STRING, length: 100, unique: true)]
    private string $name;

    /**
     * Human-readable display name (e.g., 'Sales Team', 'Technical Support')
     */
    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $displayName;

    /**
     * Target service level percentage (e.g., 0.80 for 80%)
     */
    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2)]
    private string $targetServiceLevel = '0.80';

    /**
     * Target answer time in seconds (e.g., 20 for "80% in 20 seconds")
     */
    #[ORM\Column(type: Types::INTEGER)]
    private int $targetAnswerTimeSeconds = 20;

    /**
     * Timestamp when the queue was created
     */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    /**
     * Timestamp when the queue was last updated
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

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function getDisplayName(): string
    {
        return $this->displayName;
    }

    public function setDisplayName(string $displayName): self
    {
        $this->displayName = $displayName;
        return $this;
    }

    public function getTargetServiceLevel(): float
    {
        return (float) $this->targetServiceLevel;
    }

    public function setTargetServiceLevel(float $targetServiceLevel): self
    {
        $this->targetServiceLevel = (string) $targetServiceLevel;
        return $this;
    }

    public function getTargetAnswerTimeSeconds(): int
    {
        return $this->targetAnswerTimeSeconds;
    }

    public function setTargetAnswerTimeSeconds(int $targetAnswerTimeSeconds): self
    {
        $this->targetAnswerTimeSeconds = $targetAnswerTimeSeconds;
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
     * Get service level description (e.g., "80% in 20 seconds")
     */
    public function getServiceLevelDescription(): string
    {
        return sprintf(
            '%d%% in %d seconds',
            (int) ($this->getTargetServiceLevel() * 100),
            $this->targetAnswerTimeSeconds
        );
    }

    /**
     * String representation of the queue
     */
    public function __toString(): string
    {
        return $this->displayName;
    }
}
