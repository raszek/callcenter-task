<?php

namespace App\Scheduling;

/**
 * Main input DTO for schedule generation containing all required data
 */
class ScheduleGenerationInputDTO
{
    /**
     * @param AgentAvailabilityDTO[] $agentAvailabilities
     * @param AgentSkillDTO[] $agentSkills
     * @param DemandForecastDTO[] $demandForecasts
     * @param array<string, mixed> $constraints
     * @param int $timeSlotGranularityMinutes
     */
    public function __construct(
        private array $agentAvailabilities,
        private array $agentSkills,
        private array $demandForecasts,
        private \DateTimeImmutable $scheduleStartDate,
        private \DateTimeImmutable $scheduleEndDate,
        private array $constraints = [],
        private int $timeSlotGranularityMinutes = 30
    ) {
    }

    /**
     * @return AgentAvailabilityDTO[]
     */
    public function getAgentAvailabilities(): array
    {
        return $this->agentAvailabilities;
    }

    /**
     * @return AgentSkillDTO[]
     */
    public function getAgentSkills(): array
    {
        return $this->agentSkills;
    }

    /**
     * @return DemandForecastDTO[]
     */
    public function getDemandForecasts(): array
    {
        return $this->demandForecasts;
    }

    public function getScheduleStartDate(): \DateTimeImmutable
    {
        return $this->scheduleStartDate;
    }

    public function getScheduleEndDate(): \DateTimeImmutable
    {
        return $this->scheduleEndDate;
    }

    /**
     * @return array<string, mixed>
     */
    public function getConstraints(): array
    {
        return $this->constraints;
    }

    public function getTimeSlotGranularityMinutes(): int
    {
        return $this->timeSlotGranularityMinutes;
    }

    /**
     * Get constraint value with default fallback
     */
    public function getConstraint(string $key, mixed $default = null): mixed
    {
        return $this->constraints[$key] ?? $default;
    }
}
