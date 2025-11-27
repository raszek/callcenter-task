<?php

namespace App\Tests\Scheduling;

use App\Scheduling\AgentAvailabilityDTO;
use App\Scheduling\AgentSkillDTO;
use App\Scheduling\DemandForecastDTO;
use App\Scheduling\ScheduleGenerationInputDTO;

/**
 * Test data builder for creating test fixtures for scheduling tests
 */
class ScheduleTestDataBuilder
{
    /**
     * Create agent availability for a full day
     */
    public static function createAgentAvailability(
        int $agentId,
        \DateTimeImmutable $date,
        int $startHour = 8,
        int $endHour = 17,
        bool $isAvailable = true
    ): AgentAvailabilityDTO {
        $startTime = $date->setTime($startHour, 0);
        $endTime = $date->setTime($endHour, 0);

        return new AgentAvailabilityDTO(
            agentId: $agentId,
            startTime: $startTime,
            endTime: $endTime,
            isAvailable: $isAvailable
        );
    }

    /**
     * Create multiple days of availability for an agent
     *
     * @return AgentAvailabilityDTO[]
     */
    public static function createWeeklyAvailability(
        int $agentId,
        \DateTimeImmutable $startDate,
        int $days = 5,
        int $startHour = 8,
        int $endHour = 17
    ): array {
        $availabilities = [];

        for ($i = 0; $i < $days; $i++) {
            $date = $startDate->modify("+{$i} days");
            $availabilities[] = self::createAgentAvailability(
                $agentId,
                $date,
                $startHour,
                $endHour
            );
        }

        return $availabilities;
    }

    /**
     * Create agent skill
     */
    public static function createAgentSkill(
        int $agentId,
        string $queueName,
        float $efficiencyCoefficient = 1.0,
        int $skillLevel = 2,
        bool $isPrimary = false
    ): AgentSkillDTO {
        return new AgentSkillDTO(
            agentId: $agentId,
            queueName: $queueName,
            efficiencyCoefficient: $efficiencyCoefficient,
            skillLevel: $skillLevel,
            isPrimary: $isPrimary
        );
    }

    /**
     * Create demand forecast for a time slot
     */
    public static function createDemandForecast(
        string $queueName,
        \DateTimeImmutable $startTime,
        int $durationMinutes = 30,
        int $forecastedCalls = 10,
        float $requiredFTE = 1.0
    ): DemandForecastDTO {
        $endTime = $startTime->add(new \DateInterval("PT{$durationMinutes}M"));

        return new DemandForecastDTO(
            queueName: $queueName,
            startTime: $startTime,
            endTime: $endTime,
            forecastedCalls: $forecastedCalls,
            requiredFTE: $requiredFTE,
            confidenceLower: $requiredFTE * 0.85,
            confidenceUpper: $requiredFTE * 1.15
        );
    }

    /**
     * Create hourly demand forecasts for a day
     *
     * @return DemandForecastDTO[]
     */
    public static function createDailyDemand(
        string $queueName,
        \DateTimeImmutable $date,
        int $startHour = 8,
        int $endHour = 17,
        float $avgRequiredFTE = 2.0,
        int $granularityMinutes = 30
    ): array {
        $forecasts = [];
        $current = $date->setTime($startHour, 0);
        $end = $date->setTime($endHour, 0);

        while ($current < $end) {
            // Vary demand throughout the day (peak at midday)
            $hour = (int) $current->format('H');
            $peakFactor = 1.0 + (0.5 * sin(($hour - 8) * M_PI / 9));
            $requiredFTE = $avgRequiredFTE * $peakFactor;

            $forecasts[] = self::createDemandForecast(
                queueName: $queueName,
                startTime: $current,
                durationMinutes: $granularityMinutes,
                forecastedCalls: (int) ($requiredFTE * 10),
                requiredFTE: $requiredFTE
            );

            $current = $current->add(new \DateInterval("PT{$granularityMinutes}M"));
        }

        return $forecasts;
    }

    /**
     * Create a complete input DTO with test data
     */
    public static function createStandardInput(
        \DateTimeImmutable $startDate,
        int $numAgents = 5,
        int $numQueues = 2,
        int $numDays = 1
    ): ScheduleGenerationInputDTO {
        $endDate = $startDate->modify("+{$numDays} days");

        $availabilities = [];
        $skills = [];
        $forecasts = [];

        $queueNames = [];
        for ($q = 1; $q <= $numQueues; $q++) {
            $queueNames[] = "queue_{$q}";
        }

        // Create agent data
        for ($agentId = 1; $agentId <= $numAgents; $agentId++) {
            // Create weekly availability
            $availabilities = array_merge(
                $availabilities,
                self::createWeeklyAvailability($agentId, $startDate, $numDays)
            );

            // Create skills - each agent can handle different queues
            foreach ($queueNames as $index => $queueName) {
                // Some agents are experts, some are proficient
                $skillLevel = ($agentId % 3 === 0) ? 3 : 2;
                $efficiency = $skillLevel === 3 ? 1.3 : 1.0;
                $isPrimary = $index === 0;

                $skills[] = self::createAgentSkill(
                    agentId: $agentId,
                    queueName: $queueName,
                    efficiencyCoefficient: $efficiency,
                    skillLevel: $skillLevel,
                    isPrimary: $isPrimary
                );
            }
        }

        // Create demand forecasts
        for ($day = 0; $day < $numDays; $day++) {
            $date = $startDate->modify("+{$day} days");

            foreach ($queueNames as $queueName) {
                $forecasts = array_merge(
                    $forecasts,
                    self::createDailyDemand($queueName, $date)
                );
            }
        }

        return new ScheduleGenerationInputDTO(
            agentAvailabilities: $availabilities,
            agentSkills: $skills,
            demandForecasts: $forecasts,
            scheduleStartDate: $startDate,
            scheduleEndDate: $endDate,
            constraints: [
                'min_hours_per_day' => 4,
                'max_hours_per_day' => 8,
                'max_consecutive_hours' => 6,
                'efficiency_weight' => 3.0
            ],
            timeSlotGranularityMinutes: 30
        );
    }
}
