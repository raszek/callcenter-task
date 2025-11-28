<?php

namespace App\Scheduling;

/**
 * Main input DTO for schedule generation containing all required data
 */
readonly class ScheduleGenerationInputDTO
{
    /**
     * @param AgentAvailabilityDTO[] $agentAvailabilities
     * @param AgentSkillDTO[] $agentSkills
     * @param DemandForecastDTO[] $demandForecasts
     * @param \DateTimeImmutable $scheduleStartDate
     * @param \DateTimeImmutable $scheduleEndDate
     * @param array<string, mixed> $constraints
     * @param int $timeSlotGranularityMinutes
     */
    public function __construct(
        public array $agentAvailabilities,
        public array $agentSkills,
        public array $demandForecasts,
        public \DateTimeImmutable $scheduleStartDate,
        public \DateTimeImmutable $scheduleEndDate,
        public array $constraints = [],
        public int $timeSlotGranularityMinutes = 30
    ) {
    }

    /**
     * Get constraint value with default fallback
     */
    public function getConstraint(string $key, mixed $default = null): mixed
    {
        return $this->constraints[$key] ?? $default;
    }
}
