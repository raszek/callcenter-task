<?php

namespace App\Scheduling;

/**
 * Output DTO containing the generated schedule and quality metrics
 */
class ScheduleGenerationOutputDTO
{
    /**
     * @param ScheduleAssignmentDTO[] $assignments
     * @param array<string, mixed> $qualityMetrics
     * @param array<string, array<string, float>> $coverageByQueueAndHour
     */
    public function __construct(
        private array $assignments,
        private array $qualityMetrics = [],
        private array $coverageByQueueAndHour = [],
        private bool $isFeasible = true,
        private array $warnings = []
    ) {
    }

    /**
     * @return ScheduleAssignmentDTO[]
     */
    public function getAssignments(): array
    {
        return $this->assignments;
    }

    /**
     * @return array<string, mixed>
     */
    public function getQualityMetrics(): array
    {
        return $this->qualityMetrics;
    }

    /**
     * @return array<string, array<string, float>>
     */
    public function getCoverageByQueueAndHour(): array
    {
        return $this->coverageByQueueAndHour;
    }

    public function isFeasible(): bool
    {
        return $this->isFeasible;
    }

    public function getWarnings(): array
    {
        return $this->warnings;
    }

    public function addWarning(string $warning): void
    {
        $this->warnings[] = $warning;
    }

    public function getQualityMetric(string $key, mixed $default = null): mixed
    {
        return $this->qualityMetrics[$key] ?? $default;
    }
}
