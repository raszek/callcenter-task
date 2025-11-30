<?php

namespace App\Scheduling;

/**
 * Implements Step 1: Initial Schedule Generation (T-7 days)
 *
 * This class generates an optimized work schedule for call center agents
 * based on availability, skills, efficiency, and forecasted demand.
 *
 * Algorithm:
 * 1. Collect agent availability declarations (from input DTO)
 * 2. Run demand forecast for upcoming week (from input DTO)
 * 3. Execute optimization model with full week horizon
 * 4. Generate initial schedule with configurable granularity
 * 5. Return schedule with quality metrics for human review
 *
 *  Key Design Decisions:
 *  1. Greedy Algorithm: Not globally optimal, but fast and produces good results
 *  2. Composite Scoring: Balances multiple objectives (efficiency, fairness, fatigue)
 *  3. Time-based Iteration: Ensures coverage at every time point
 *  4. Constraint Enforcement: Hard constraints checked during candidate selection
 *  5. Post-processing: Merges assignments for cleaner output and better UX
 */
class InitialScheduleGenerator
{
    private const DEFAULT_MAX_HOURS_PER_DAY = 8;
    private const DEFAULT_MAX_CONSECUTIVE_HOURS = 6;
    private const DEFAULT_OVERSTAFFING_THRESHOLD = 1.2;
    private const DEFAULT_EFFICIENCY_WEIGHT = 3.0;

    /**
     * Generate initial schedule for the week
     */
    public function generate(ScheduleGenerationInputDTO $input): ScheduleGenerationOutputDTO
    {
        // Step 1: Initialize data structures
        $timeSlots = $this->generateTimeSlots($input);
        $agentAvailabilityMap = $this->buildAvailabilityMap($input->agentAvailabilities);
        $agentSkillMap = $this->buildSkillMap($input->agentSkills);
        $demandMap = $this->buildDemandMap($input->demandForecasts);

        // Step 2: Execute optimization
        $assignments = $this->optimizeSchedule(
            $timeSlots,
            $agentAvailabilityMap,
            $agentSkillMap,
            $demandMap,
            $input
        );

        // Step 3: Calculate quality metrics and coverage
        $qualityMetrics = $this->calculateQualityMetrics($assignments, $demandMap, $input);
        $coverage = $this->calculateCoverage($assignments, $timeSlots);

        // Step 4: Validate feasibility and generate warnings
        $warnings = $this->validateSchedule($assignments, $demandMap, $coverage, $input);
        $isFeasible = empty(array_filter($warnings, fn($w) => str_contains($w, 'CRITICAL')));

        return new ScheduleGenerationOutputDTO(
            assignments: $assignments,
            qualityMetrics: $qualityMetrics,
            coverageByQueueAndHour: $coverage,
            isFeasible: $isFeasible,
            warnings: $warnings
        );
    }

    /**
     * Generate time slots for the entire schedule period
     *
     * @return array<int, \DateTimeImmutable>
     * @throws \Exception
     */
    private function generateTimeSlots(ScheduleGenerationInputDTO $input): array
    {
        $slots = [];
        $current = $input->scheduleStartDate;
        $end = $input->scheduleEndDate;
        $interval = new \DateInterval('PT' . $input->timeSlotGranularityMinutes . 'M');

        while ($current < $end) {
            $slots[] = $current;
            $current = $current->add($interval);
        }

        return $slots;
    }

    /**
     * @param AgentAvailabilityDTO[] $availabilities
     *
     * Build availability lookup map: [agentId][timestamp] => bool
     */
    private function buildAvailabilityMap(array $availabilities): array
    {
        $map = [];

        foreach ($availabilities as $availability) {
            $agentId = $availability->agentId;
            if (!isset($map[$agentId])) {
                $map[$agentId] = [];
            }

            // Store availability for the time range
            $map[$agentId][] = [
                'start' => $availability->startTime,
                'end' => $availability->endTime,
                'available' => $availability->isAvailable
            ];
        }

        return $map;
    }

    /**
     * Build skill lookup map: [agentId][queueName] => AgentSkillDTO
     *
     * @param AgentSkillDTO[] $skills
     */
    private function buildSkillMap(array $skills): array
    {
        $map = [];

        foreach ($skills as $skill) {
            $agentId = $skill->agentId;
            $queueName = $skill->queueName;

            if (!isset($map[$agentId])) {
                $map[$agentId] = [];
            }

            $map[$agentId][$queueName] = $skill;
        }

        return $map;
    }

    /**
     * Build demand lookup map: [queueName][timestamp] => DemandForecastDTO
     *
     * @param DemandForecastDTO[] $forecasts
     */
    private function buildDemandMap(array $forecasts): array
    {
        $map = [];

        foreach ($forecasts as $forecast) {
            $queueName = $forecast->queueName;

            if (!isset($map[$queueName])) {
                $map[$queueName] = [];
            }

            $key = $forecast->startTime->getTimestamp();
            $map[$queueName][$key] = $forecast;
        }

        return $map;
    }

    /**
     * Core optimization algorithm using greedy approach with efficiency weighting
     *
     * Strategy:
     * - Iterate through each time slot
     * - For each queue with demand, assign agents based on composite score
     * - Composite score = efficiency * availability * constraint_penalties
     * - Track assignments to respect daily/weekly limits
     */
    private function optimizeSchedule(
        array $timeSlots,
        array $agentAvailabilityMap,
        array $agentSkillMap,
        array $demandMap,
        ScheduleGenerationInputDTO $input
    ): array {
        $assignments = [];
        $agentDailyHours = []; // Track hours per agent per day
        $agentCurrentShift = []; // Track current shift start time

        $granularityMinutes = $input->timeSlotGranularityMinutes;
        $slotHours = $granularityMinutes / 60;

        // Group time slots by queue and process demand
        foreach ($timeSlots as $timeSlot) {
            foreach ($demandMap as $queueName => $demandSlots) {
                $timestamp = $timeSlot->getTimestamp();

                if (!isset($demandSlots[$timestamp])) {
                    continue;
                }

                /**
                 * @var DemandForecastDTO $demand
                 */
                $demand = $demandSlots[$timestamp];
                $requiredFTE = $demand->requiredFTE;

                // Calculate how many agents needed for this slot
                $agentsNeeded = (int) ceil($requiredFTE / $slotHours);

                // Find best agents for this slot
                $candidateAgents = $this->findCandidateAgents(
                    $queueName,
                    $timeSlot,
                    $agentAvailabilityMap,
                    $agentSkillMap,
                    $agentDailyHours,
                    $agentCurrentShift,
                    $input
                );

                // Sort by composite score (descending)
                usort($candidateAgents, fn($a, $b) => $b['score'] <=> $a['score']);

                // Assign top N agents
                $assignedCount = 0;
                foreach ($candidateAgents as $candidate) {
                    if ($assignedCount >= $agentsNeeded) {
                        break;
                    }

                    $agentId = $candidate['agentId'];

                    /**
                     * @var AgentSkillDTO $skill
                     */
                    $skill = $candidate['skill'];

                    // Create assignment
                    $endTime = $timeSlot->add(new \DateInterval('PT' . $granularityMinutes . 'M'));

                    $assignment = new ScheduleAssignmentDTO(
                        agentId: $agentId,
                        queueName: $queueName,
                        startTime: $timeSlot,
                        endTime: $endTime,
                        efficiencyScore: $skill->efficiencyCoefficient,
                        assignmentType: $skill->isPrimary ? 'primary' : 'secondary'
                    );

                    $assignments[] = $assignment;

                    // Update tracking
                    $day = $timeSlot->format('Y-m-d');
                    if (!isset($agentDailyHours[$agentId])) {
                        $agentDailyHours[$agentId] = [];
                    }
                    if (!isset($agentDailyHours[$agentId][$day])) {
                        $agentDailyHours[$agentId][$day] = 0;
                    }
                    $agentDailyHours[$agentId][$day] += $slotHours;

                    // Track shift continuity
                    if (!isset($agentCurrentShift[$agentId])) {
                        $agentCurrentShift[$agentId] = [
                            'start' => $timeSlot,
                            'hours' => $slotHours
                        ];
                    } else {
                        $agentCurrentShift[$agentId]['hours'] += $slotHours;
                    }

                    $assignedCount++;
                }
            }
        }

        // Post-process: merge consecutive assignments into longer shifts
        return $this->mergeConsecutiveAssignments($assignments);
    }

    /**
     * Find candidate agents for a specific queue and time slot
     */
    private function findCandidateAgents(
        string $queueName,
        \DateTimeImmutable $timeSlot,
        array $agentAvailabilityMap,
        array $agentSkillMap,
        array $agentDailyHours,
        array $agentCurrentShift,
        ScheduleGenerationInputDTO $input
    ): array {
        $candidates = [];
        $day = $timeSlot->format('Y-m-d');

        $maxHoursPerDay = $input->getConstraint('max_hours_per_day', self::DEFAULT_MAX_HOURS_PER_DAY);
        $maxConsecutiveHours = $input->getConstraint('max_consecutive_hours', self::DEFAULT_MAX_CONSECUTIVE_HOURS);
        $efficiencyWeight = $input->getConstraint('efficiency_weight', self::DEFAULT_EFFICIENCY_WEIGHT);

        foreach ($agentSkillMap as $agentId => $queues) {
            // Check if agent has skill for this queue
            if (!isset($queues[$queueName])) {
                continue;
            }

            $skill = $queues[$queueName];

            // Check availability
            if (!$this->isAgentAvailable($agentId, $timeSlot, $agentAvailabilityMap)) {
                continue;
            }

            // Check daily hours limit
            $currentDailyHours = $agentDailyHours[$agentId][$day] ?? 0;
            $slotHours = $input->timeSlotGranularityMinutes / 60;

            if ($currentDailyHours + $slotHours > $maxHoursPerDay) {
                continue;
            }

            // Check consecutive hours limit
            $consecutiveHours = $agentCurrentShift[$agentId]['hours'] ?? 0;
            if ($consecutiveHours + $slotHours > $maxConsecutiveHours) {
                continue;
            }

            // Calculate composite score
            $score = $this->calculateCompositeScore(
                $skill,
                $currentDailyHours,
                $consecutiveHours,
                $efficiencyWeight
            );

            $candidates[] = [
                'agentId' => $agentId,
                'skill' => $skill,
                'score' => $score
            ];
        }

        return $candidates;
    }

    /**
     * Check if agent is available at given time slot
     */
    private function isAgentAvailable(
        int $agentId,
        \DateTimeImmutable $timeSlot,
        array $agentAvailabilityMap
    ): bool {
        if (!isset($agentAvailabilityMap[$agentId])) {
            return false;
        }

        foreach ($agentAvailabilityMap[$agentId] as $availability) {
            if ($timeSlot >= $availability['start'] &&
                $timeSlot < $availability['end'] &&
                $availability['available']) {
                return true;
            }
        }

        return false;
    }

    /**
     * Calculate composite score for agent assignment
     *
     * Score = Efficiency * Skill_Level_Multiplier * Fatigue_Factor * Balance_Factor
     */
    private function calculateCompositeScore(
        AgentSkillDTO $skill,
        float $currentDailyHours,
        float $consecutiveHours,
        float $efficiencyWeight
    ): float {
        // Base efficiency score
        $efficiencyScore = $skill->efficiencyCoefficient * $efficiencyWeight;

        // Skill level multiplier (prefer experts)
        $skillMultiplier = match($skill->skillLevel) {
            3 => 1.5,  // Expert
            2 => 1.0,  // Proficient
            1 => 0.7,  // Capable
            default => 0.5
        };

        // Primary queue bonus
        $primaryBonus = $skill->isPrimary ? 1.2 : 1.0;

        // Fatigue factor (reduce score for agents already working many hours)
        $fatigueFactor = 1.0 - ($consecutiveHours / 12.0); // Decreases with consecutive hours

        // Balance factor (prefer agents with fewer daily hours for fairness)
        $balanceFactor = 1.0 - ($currentDailyHours / 10.0);

        return $efficiencyScore * $skillMultiplier * $primaryBonus * $fatigueFactor * $balanceFactor;
    }

    /**
     * Merge consecutive assignments into longer continuous shifts
     *
     * @param ScheduleAssignmentDTO[] $assignments
     */
    private function mergeConsecutiveAssignments(array $assignments): array
    {
        // Group by agent and queue
        $grouped = [];
        foreach ($assignments as $assignment) {
            $key = $assignment->agentId . '_' . $assignment->queueName;
            if (!isset($grouped[$key])) {
                $grouped[$key] = [];
            }
            $grouped[$key][] = $assignment;
        }

        $merged = [];

        foreach ($grouped as $group) {
            // Sort by start time
            usort($group, fn($a, $b) => $a->startTime <=> $b->startTime);

            $currentShift = null;

            foreach ($group as $assignment) {
                if ($currentShift === null) {
                    $currentShift = $assignment;
                    continue;
                }

                // Check if consecutive (end time of current = start time of next)
                if ($currentShift->endTime == $assignment->startTime) {
                    // Merge: extend end time
                    $currentShift = new ScheduleAssignmentDTO(
                        agentId: $currentShift->agentId,
                        queueName: $currentShift->queueName,
                        startTime: $currentShift->startTime,
                        endTime: $assignment->endTime,
                        efficiencyScore: $currentShift->efficiencyScore,
                        assignmentType: $currentShift->assignmentType,
                    );
                } else {
                    // Not consecutive, save current and start new
                    $merged[] = $currentShift;
                    $currentShift = $assignment;
                }
            }

            // Add last shift
            if ($currentShift !== null) {
                $merged[] = $currentShift;
            }
        }

        return $merged;
    }

    /**
     * Calculate coverage for each queue and hour
     */
    private function calculateCoverage(array $assignments, array $timeSlots): array
    {
        $coverage = [];

        foreach ($assignments as $assignment) {
            $queue = $assignment->queueName;
            $start = $assignment->startTime;
            $end = $assignment->endTime;

            if (!isset($coverage[$queue])) {
                $coverage[$queue] = [];
            }

            // Add coverage for each hour in the assignment
            $current = $start;
            while ($current < $end) {
                $hourKey = $current->format('Y-m-d H:00');

                if (!isset($coverage[$queue][$hourKey])) {
                    $coverage[$queue][$hourKey] = 0;
                }

                $coverage[$queue][$hourKey] += $assignment->efficiencyScore;

                $current = $current->add(new \DateInterval('PT1H'));
            }
        }

        return $coverage;
    }

    /**
     * Calculate quality metrics for the generated schedule
     */
    private function calculateQualityMetrics(
        array $assignments,
        array $demandMap,
    ): array {
        $metrics = [
            'total_assignments' => count($assignments),
            'total_agent_hours' => 0,
            'average_efficiency' => 0,
            'understaffing_slots' => 0,
            'overstaffing_slots' => 0,
            'coverage_percentage' => 0,
            'fairness_index' => 0
        ];

        $totalEfficiency = 0;
        $agentHours = [];

        foreach ($assignments as $assignment) {
            $hours = $assignment->getDurationInHours();
            $metrics['total_agent_hours'] += $hours;
            $totalEfficiency += $assignment->efficiencyScore;

            $agentId = $assignment->agentId;
            if (!isset($agentHours[$agentId])) {
                $agentHours[$agentId] = 0;
            }
            $agentHours[$agentId] += $hours;
        }

        if (count($assignments) > 0) {
            $metrics['average_efficiency'] = $totalEfficiency / count($assignments);
        }

        // Calculate fairness index (Jain's fairness index)
        if (count($agentHours) > 0) {
            $sumHours = array_sum($agentHours);
            $sumSquares = array_sum(array_map(fn($h) => $h * $h, $agentHours));
            $n = count($agentHours);

            $metrics['fairness_index'] = ($sumHours * $sumHours) / ($n * $sumSquares);
        }

        // Calculate coverage and staffing quality
        $coverage = $this->calculateCoverage($assignments, []);
        $coveredSlots = 0;
        $totalSlots = 0;

        foreach ($demandMap as $queueName => $slots) {
            foreach ($slots as $demand) {
                $totalSlots++;
                $hourKey = $demand->startTime->format('Y-m-d H:00');
                $actualCoverage = $coverage[$queueName][$hourKey] ?? 0;
                $required = $demand->requiredFTE;

                if ($actualCoverage >= $required * 0.9) { // 90% threshold
                    $coveredSlots++;
                }

                if ($actualCoverage < $required) {
                    $metrics['understaffing_slots']++;
                } elseif ($actualCoverage > $required * self::DEFAULT_OVERSTAFFING_THRESHOLD) {
                    $metrics['overstaffing_slots']++;
                }
            }
        }

        if ($totalSlots > 0) {
            $metrics['coverage_percentage'] = ($coveredSlots / $totalSlots) * 100;
        }

        return $metrics;
    }

    /**
     * Validate schedule and generate warnings
     */
    private function validateSchedule(
        array $assignments,
        array $demandMap,
        array $coverage,
        ScheduleGenerationInputDTO $input
    ): array {
        $warnings = [];

        // Check for critical understaffing
        foreach ($demandMap as $queueName => $slots) {
            foreach ($slots as $demand) {
                $hourKey = $demand->startTime->format('Y-m-d H:00');
                $actualCoverage = $coverage[$queueName][$hourKey] ?? 0;
                $required = $demand->requiredFTE;

                if ($actualCoverage < $required * 0.7) { // Less than 70% coverage
                    $warnings[] = sprintf(
                        'CRITICAL: Severe understaffing in queue "%s" at %s (%.1f FTE required, %.1f assigned)',
                        $queueName,
                        $hourKey,
                        $required,
                        $actualCoverage
                    );
                } elseif ($actualCoverage < $required * 0.9) {
                    $warnings[] = sprintf(
                        'WARNING: Understaffing in queue "%s" at %s (%.1f FTE required, %.1f assigned)',
                        $queueName,
                        $hourKey,
                        $required,
                        $actualCoverage
                    );
                }
            }
        }

        // Check for agent overload
        $agentDailyHours = [];
        foreach ($assignments as $assignment) {
            $agentId = $assignment->agentId;
            $day = $assignment->startTime->format('Y-m-d');
            $key = $agentId . '_' . $day;

            if (!isset($agentDailyHours[$key])) {
                $agentDailyHours[$key] = 0;
            }

            $agentDailyHours[$key] += $assignment->getDurationInHours();
        }

        $maxHoursPerDay = $input->getConstraint('max_hours_per_day', self::DEFAULT_MAX_HOURS_PER_DAY);

        foreach ($agentDailyHours as $key => $hours) {
            if ($hours > $maxHoursPerDay) {
                [$agentId, $day] = explode('_', $key);
                $warnings[] = sprintf(
                    'WARNING: Agent %d exceeds max hours on %s (%.1f hours assigned, max %.1f)',
                    $agentId,
                    $day,
                    $hours,
                    $maxHoursPerDay
                );
            }
        }

        return $warnings;
    }
}
