<?php

namespace App\Entity;

use App\Repository\AgentSkillRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AgentSkillRepository::class)]
class AgentSkill
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private ?float $efficiencyCoefficient = null;

    #[ORM\Column]
    private ?int $skillLevel = null;

    #[ORM\Column]
    private ?bool $isPrimary = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Agent $agent = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Queue $queue = null;

    public function __construct(
        float $efficiencyCoefficient,
        int $skillLevel,
        bool $isPrimary,
        Agent $agent,
        Queue $queue
    ) {
        $this->efficiencyCoefficient = $efficiencyCoefficient;
        $this->skillLevel = $skillLevel;
        $this->isPrimary = $isPrimary;
        $this->agent = $agent;
        $this->queue = $queue;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEfficiencyCoefficient(): ?float
    {
        return $this->efficiencyCoefficient;
    }

    public function getSkillLevel(): ?int
    {
        return $this->skillLevel;
    }

    public function isPrimary(): ?bool
    {
        return $this->isPrimary;
    }

    public function getAgent(): ?Agent
    {
        return $this->agent;
    }

    public function getQueue(): ?Queue
    {
        return $this->queue;
    }
}
