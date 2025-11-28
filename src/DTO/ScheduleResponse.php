<?php

namespace App\DTO;

use App\Scheduling\ScheduleAssignmentDTO;
use App\Scheduling\ScheduleGenerationOutputDTO;

class ScheduleResponse
{
    /**
     * @param array<int, array<string, mixed>> $assignments
     * @param array<string, mixed> $qualityMetrics
     * @param array<string, array<string, float>> $coverageByQueueAndHour
     * @param string[] $warnings
     */
    public function __construct(
        public array $assignments,
        public array $qualityMetrics,
        public array $coverageByQueueAndHour,
        public bool $isFeasible,
        public array $warnings
    ) {
    }

    /**
     * Create from ScheduleGenerationOutputDTO
     */
    public static function fromScheduleOutput(ScheduleGenerationOutputDTO $output): self
    {
        $assignments = array_map(
            fn(ScheduleAssignmentDTO $assignment) => [
                'agent_id' => $assignment->agentId,
                'queue_name' => $assignment->queueName,
                'start_time' => $assignment->startTime->format(\DateTimeInterface::ATOM),
                'end_time' => $assignment->endTime->format(\DateTimeInterface::ATOM),
                'duration_hours' => $assignment->getDurationInHours(),
                'efficiency_score' => $assignment->efficiencyScore,
                'assignment_type' => $assignment->assignmentType
            ],
            $output->assignments
        );

        return new self(
            assignments: $assignments,
            qualityMetrics: $output->qualityMetrics,
            coverageByQueueAndHour: $output->coverageByQueueAndHour,
            isFeasible: $output->isFeasible,
            warnings: $output->getWarnings()
        );
    }
}
