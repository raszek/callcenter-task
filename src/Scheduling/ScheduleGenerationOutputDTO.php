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
        public array $assignments,
        public array $qualityMetrics = [],
        public array $coverageByQueueAndHour = [],
        public bool $isFeasible = true,
        private array $warnings = []
    ) {
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
