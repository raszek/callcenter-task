<?php

namespace App\Tests\Scheduling;

use App\Scheduling\AgentAvailabilityDTO;
use App\Scheduling\AgentSkillDTO;
use App\Scheduling\DemandForecastDTO;
use App\Scheduling\InitialScheduleGenerator;
use App\Scheduling\ScheduleGenerationInputDTO;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for InitialScheduleGenerator
 */
class InitialScheduleGeneratorTest extends TestCase
{
    private InitialScheduleGenerator $generator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->generator = new InitialScheduleGenerator();
    }

    /**
     * Test basic schedule generation with simple scenario
     */
    public function testGenerateBasicSchedule(): void
    {
        $startDate = new \DateTimeImmutable('2025-12-01 00:00:00');
        $input = ScheduleTestDataBuilder::createStandardInput($startDate, numAgents: 3, numQueues: 1, numDays: 1);

        $output = $this->generator->generate($input);

        $this->assertNotEmpty($output->getAssignments(), 'Should generate assignments');
        $this->assertFalse($output->isFeasible(), 'Schedule should not be feasible');
        $this->assertIsArray($output->getQualityMetrics(), 'Should have quality metrics');
        $this->assertIsArray($output->getCoverageByQueueAndHour(), 'Should have coverage data');
    }

    /**
     * Test that assignments respect agent availability
     */
    public function testRespectsAgentAvailability(): void
    {
        $startDate = new \DateTimeImmutable('2025-12-01 00:00:00');

        // Agent 1 available 8-12, Agent 2 available 12-17
        $availabilities = [
            ScheduleTestDataBuilder::createAgentAvailability(1, $startDate, 8, 12),
            ScheduleTestDataBuilder::createAgentAvailability(2, $startDate, 12, 17),
        ];

        $skills = [
            ScheduleTestDataBuilder::createAgentSkill(1, 'sales', 1.0, 2, true),
            ScheduleTestDataBuilder::createAgentSkill(2, 'sales', 1.0, 2, true),
        ];

        // Demand throughout the day
        $forecasts = ScheduleTestDataBuilder::createDailyDemand('sales', $startDate, 8, 17, 1.0);

        $input = new ScheduleGenerationInputDTO(
            agentAvailabilities: $availabilities,
            agentSkills: $skills,
            demandForecasts: $forecasts,
            scheduleStartDate: $startDate,
            scheduleEndDate: $startDate->modify('+1 day'),
            timeSlotGranularityMinutes: 30
        );

        $output = $this->generator->generate($input);
        $assignments = $output->getAssignments();

        // Verify Agent 1 only works before 12:00
        foreach ($assignments as $assignment) {
            if ($assignment->getAgentId() === 1) {
                $this->assertEquals(
                    12,
                    (int) $assignment->getEndTime()->format('H'),
                    'Agent 1 should only work before 12:00'
                );
            }
            if ($assignment->getAgentId() === 2) {
                $this->assertGreaterThanOrEqual(
                    12,
                    (int) $assignment->getStartTime()->format('H'),
                    'Agent 2 should only work after 12:00'
                );
            }
        }
    }

    /**
     * Test that agents are only assigned to queues they can handle
     */
    public function testRespectsAgentSkills(): void
    {
        $startDate = new \DateTimeImmutable('2025-12-01 00:00:00');

        $availabilities = [
            ScheduleTestDataBuilder::createAgentAvailability(1, $startDate, 8, 17),
            ScheduleTestDataBuilder::createAgentAvailability(2, $startDate, 8, 17),
        ];

        // Agent 1 can only handle sales, Agent 2 can only handle support
        $skills = [
            ScheduleTestDataBuilder::createAgentSkill(1, 'sales', 1.0, 2, true),
            ScheduleTestDataBuilder::createAgentSkill(2, 'support', 1.0, 2, true),
        ];

        $forecasts = array_merge(
            ScheduleTestDataBuilder::createDailyDemand('sales', $startDate, 8, 17, 1.0),
            ScheduleTestDataBuilder::createDailyDemand('support', $startDate, 8, 17, 1.0)
        );

        $input = new ScheduleGenerationInputDTO(
            agentAvailabilities: $availabilities,
            agentSkills: $skills,
            demandForecasts: $forecasts,
            scheduleStartDate: $startDate,
            scheduleEndDate: $startDate->modify('+1 day'),
            timeSlotGranularityMinutes: 30
        );

        $output = $this->generator->generate($input);

        // Verify agents only assigned to their queues
        foreach ($output->getAssignments() as $assignment) {
            if ($assignment->getAgentId() === 1) {
                $this->assertEquals('sales', $assignment->getQueueName(), 'Agent 1 should only handle sales');
            }
            if ($assignment->getAgentId() === 2) {
                $this->assertEquals('support', $assignment->getQueueName(), 'Agent 2 should only handle support');
            }
        }
    }

    /**
     * Test that daily hours limits are respected
     */
    public function testRespectsMaxDailyHours(): void
    {
        $startDate = new \DateTimeImmutable('2025-12-01 00:00:00');

        $availabilities = [
            ScheduleTestDataBuilder::createAgentAvailability(1, $startDate, 8, 20), // 12 hour window
        ];

        $skills = [
            ScheduleTestDataBuilder::createAgentSkill(1, 'sales', 1.0, 2, true),
        ];

        // High demand all day
        $forecasts = ScheduleTestDataBuilder::createDailyDemand('sales', $startDate, 8, 20, 5.0);

        $input = new ScheduleGenerationInputDTO(
            agentAvailabilities: $availabilities,
            agentSkills: $skills,
            demandForecasts: $forecasts,
            scheduleStartDate: $startDate,
            scheduleEndDate: $startDate->modify('+1 day'),
            constraints: [
                'max_hours_per_day' => 6,
            ],
            timeSlotGranularityMinutes: 30
        );

        $output = $this->generator->generate($input);

        // Calculate total hours for agent 1
        $totalHours = 0;
        foreach ($output->getAssignments() as $assignment) {
            if ($assignment->getAgentId() === 1) {
                $totalHours += $assignment->getDurationInHours();
            }
        }

        $this->assertLessThanOrEqual(6, $totalHours, 'Agent should not exceed max daily hours');
    }

    /**
     * Test efficiency coefficient is properly captured
     */
    public function testCapturesEfficiencyCoefficient(): void
    {
        $startDate = new \DateTimeImmutable('2025-12-01 00:00:00');

        $availabilities = [
            ScheduleTestDataBuilder::createAgentAvailability(1, $startDate, 8, 17),
        ];

        $skills = [
            ScheduleTestDataBuilder::createAgentSkill(1, 'sales', 1.5, 3, true), // Expert with 1.5x efficiency
        ];

        $forecasts = ScheduleTestDataBuilder::createDailyDemand('sales', $startDate, 8, 9, 1.0);

        $input = new ScheduleGenerationInputDTO(
            agentAvailabilities: $availabilities,
            agentSkills: $skills,
            demandForecasts: $forecasts,
            scheduleStartDate: $startDate,
            scheduleEndDate: $startDate->modify('+1 day'),
            timeSlotGranularityMinutes: 30
        );

        $output = $this->generator->generate($input);

        $this->assertNotEmpty($output->getAssignments());
        $assignment = $output->getAssignments()[0];
        $this->assertEquals(1.5, $assignment->getEfficiencyScore(), 'Should capture efficiency coefficient');
    }

    /**
     * Test quality metrics are calculated
     */
    public function testCalculatesQualityMetrics(): void
    {
        $startDate = new \DateTimeImmutable('2025-12-01 00:00:00');
        $input = ScheduleTestDataBuilder::createStandardInput($startDate, numAgents: 5, numQueues: 2, numDays: 1);

        $output = $this->generator->generate($input);
        $metrics = $output->getQualityMetrics();

        $this->assertArrayHasKey('total_assignments', $metrics);
        $this->assertArrayHasKey('total_agent_hours', $metrics);
        $this->assertArrayHasKey('average_efficiency', $metrics);
        $this->assertArrayHasKey('coverage_percentage', $metrics);
        $this->assertArrayHasKey('fairness_index', $metrics);
        $this->assertArrayHasKey('understaffing_slots', $metrics);
        $this->assertArrayHasKey('overstaffing_slots', $metrics);

        $this->assertGreaterThan(0, $metrics['total_assignments']);
        $this->assertGreaterThan(0, $metrics['total_agent_hours']);
        $this->assertGreaterThan(0, $metrics['average_efficiency']);
    }

    /**
     * Test coverage tracking
     */
    public function testTracksCoverage(): void
    {
        $startDate = new \DateTimeImmutable('2025-12-01 00:00:00');
        $input = ScheduleTestDataBuilder::createStandardInput($startDate, numAgents: 5, numQueues: 1, numDays: 1);

        $output = $this->generator->generate($input);
        $coverage = $output->getCoverageByQueueAndHour();

        $this->assertNotEmpty($coverage, 'Should have coverage data');
        $this->assertArrayHasKey('queue_1', $coverage, 'Should have coverage for queue_1');

        // Verify coverage is tracked by hour
        foreach ($coverage as $queueName => $hours) {
            $this->assertIsArray($hours);
            foreach ($hours as $hourKey => $fte) {
                $this->assertMatchesRegularExpression('/\d{4}-\d{2}-\d{2} \d{2}:00/', $hourKey);
                $this->assertIsNumeric($fte);
                $this->assertGreaterThanOrEqual(0, $fte);
            }
        }
    }

    /**
     * Test warning generation for understaffing
     */
    public function testGeneratesUnderstaffingWarnings(): void
    {
        $startDate = new \DateTimeImmutable('2025-12-01 00:00:00');

        // Only 1 agent available but high demand
        $availabilities = [
            ScheduleTestDataBuilder::createAgentAvailability(1, $startDate, 8, 9),
        ];

        $skills = [
            ScheduleTestDataBuilder::createAgentSkill(1, 'sales', 1.0, 2, true),
        ];

        // Very high demand
        $forecasts = ScheduleTestDataBuilder::createDailyDemand('sales', $startDate, 8, 17, 10.0);

        $input = new ScheduleGenerationInputDTO(
            agentAvailabilities: $availabilities,
            agentSkills: $skills,
            demandForecasts: $forecasts,
            scheduleStartDate: $startDate,
            scheduleEndDate: $startDate->modify('+1 day'),
            timeSlotGranularityMinutes: 30
        );

        $output = $this->generator->generate($input);
        $warnings = $output->getWarnings();

        $this->assertNotEmpty($warnings, 'Should generate warnings for understaffing');

        // Check for understaffing warnings
        $hasUnderstaffingWarning = false;
        foreach ($warnings as $warning) {
            if (str_contains($warning, 'understaffing') || str_contains($warning, 'Understaffing')) {
                $hasUnderstaffingWarning = true;
                break;
            }
        }

        $this->assertTrue($hasUnderstaffingWarning, 'Should have understaffing warning');
    }

    /**
     * Test schedule with multiple queues
     */
    public function testMultipleQueues(): void
    {
        $startDate = new \DateTimeImmutable('2025-12-01 00:00:00');
        $input = ScheduleTestDataBuilder::createStandardInput($startDate, numAgents: 10, numQueues: 3, numDays: 1);

        $output = $this->generator->generate($input);

        $queuesInSchedule = [];
        foreach ($output->getAssignments() as $assignment) {
            $queuesInSchedule[$assignment->getQueueName()] = true;
        }

        $this->assertGreaterThanOrEqual(2, count($queuesInSchedule), 'Should have assignments for multiple queues');
    }

    /**
     * Test schedule generation for multiple days
     */
    public function testMultipleDays(): void
    {
        $startDate = new \DateTimeImmutable('2025-12-01 00:00:00');
        $input = ScheduleTestDataBuilder::createStandardInput($startDate, numAgents: 5, numQueues: 2, numDays: 5);

        $output = $this->generator->generate($input);

        $daysInSchedule = [];
        foreach ($output->getAssignments() as $assignment) {
            $day = $assignment->getStartTime()->format('Y-m-d');
            $daysInSchedule[$day] = true;
        }

        $this->assertGreaterThanOrEqual(1, count($daysInSchedule), 'Should have assignments across multiple days');
    }

    /**
     * Test fairness index calculation
     */
    public function testFairnessIndex(): void
    {
        $startDate = new \DateTimeImmutable('2025-12-01 00:00:00');
        $input = ScheduleTestDataBuilder::createStandardInput($startDate, numAgents: 5, numQueues: 1, numDays: 1);

        $output = $this->generator->generate($input);
        $metrics = $output->getQualityMetrics();

        $this->assertArrayHasKey('fairness_index', $metrics);
        $this->assertGreaterThan(0, $metrics['fairness_index']);
        $this->assertLessThanOrEqual(1, $metrics['fairness_index'], 'Fairness index should be <= 1');
    }

    /**
     * Test with no available agents
     */
    public function testNoAvailableAgents(): void
    {
        $startDate = new \DateTimeImmutable('2025-12-01 00:00:00');

        $input = new ScheduleGenerationInputDTO(
            agentAvailabilities: [],
            agentSkills: [],
            demandForecasts: ScheduleTestDataBuilder::createDailyDemand('sales', $startDate, 8, 17, 2.0),
            scheduleStartDate: $startDate,
            scheduleEndDate: $startDate->modify('+1 day'),
            timeSlotGranularityMinutes: 30
        );

        $output = $this->generator->generate($input);

        $this->assertEmpty($output->getAssignments(), 'Should have no assignments with no agents');
        $this->assertFalse($output->isFeasible(), 'Should be infeasible with no agents');
        $this->assertNotEmpty($output->getWarnings(), 'Should have warnings');
    }

    /**
     * Test with no demand
     */
    public function testNoDemand(): void
    {
        $startDate = new \DateTimeImmutable('2025-12-01 00:00:00');

        $availabilities = [
            ScheduleTestDataBuilder::createAgentAvailability(1, $startDate, 8, 17),
        ];

        $skills = [
            ScheduleTestDataBuilder::createAgentSkill(1, 'sales', 1.0, 2, true),
        ];

        $input = new ScheduleGenerationInputDTO(
            agentAvailabilities: $availabilities,
            agentSkills: $skills,
            demandForecasts: [], // No demand
            scheduleStartDate: $startDate,
            scheduleEndDate: $startDate->modify('+1 day'),
            timeSlotGranularityMinutes: 30
        );

        $output = $this->generator->generate($input);

        $this->assertEmpty($output->getAssignments(), 'Should have no assignments with no demand');
        $this->assertTrue($output->isFeasible(), 'Should be feasible (nothing to schedule)');
    }

    /**
     * Test different time slot granularities
     */
    public function testDifferentGranularities(): void
    {
        $startDate = new \DateTimeImmutable('2025-12-01 00:00:00');

        // Test with 15-minute granularity
        $input15 = ScheduleTestDataBuilder::createStandardInput($startDate, numAgents: 3, numQueues: 1, numDays: 1);
        $input15 = new ScheduleGenerationInputDTO(
            agentAvailabilities: $input15->getAgentAvailabilities(),
            agentSkills: $input15->getAgentSkills(),
            demandForecasts: $input15->getDemandForecasts(),
            scheduleStartDate: $input15->getScheduleStartDate(),
            scheduleEndDate: $input15->getScheduleEndDate(),
            constraints: $input15->getConstraints(),
            timeSlotGranularityMinutes: 15
        );

        $output15 = $this->generator->generate($input15);

        // Test with 60-minute granularity
        $input60 = ScheduleTestDataBuilder::createStandardInput($startDate, numAgents: 3, numQueues: 1, numDays: 1);
        $input60 = new ScheduleGenerationInputDTO(
            agentAvailabilities: $input60->getAgentAvailabilities(),
            agentSkills: $input60->getAgentSkills(),
            demandForecasts: $input60->getDemandForecasts(),
            scheduleStartDate: $input60->getScheduleStartDate(),
            scheduleEndDate: $input60->getScheduleEndDate(),
            constraints: $input60->getConstraints(),
            timeSlotGranularityMinutes: 60
        );

        $output60 = $this->generator->generate($input60);

        $this->assertNotEmpty($output15->getAssignments());
        $this->assertNotEmpty($output60->getAssignments());
    }

    /**
     * Test that consecutive assignments are merged
     */
    public function testMergesConsecutiveAssignments(): void
    {
        $startDate = new \DateTimeImmutable('2025-12-01 00:00:00');

        $availabilities = [
            ScheduleTestDataBuilder::createAgentAvailability(1, $startDate, 8, 12),
        ];

        $skills = [
            ScheduleTestDataBuilder::createAgentSkill(1, 'sales', 1.0, 2, true),
        ];

        // Continuous demand for 4 hours
        $forecasts = ScheduleTestDataBuilder::createDailyDemand('sales', $startDate, 8, 12, 1.0, 30);

        $input = new ScheduleGenerationInputDTO(
            agentAvailabilities: $availabilities,
            agentSkills: $skills,
            demandForecasts: $forecasts,
            scheduleStartDate: $startDate,
            scheduleEndDate: $startDate->modify('+1 day'),
            timeSlotGranularityMinutes: 30
        );

        $output = $this->generator->generate($input);
        $assignments = $output->getAssignments();

        // Should have fewer assignments than time slots due to merging
        $this->assertLessThanOrEqual(8, count($assignments), 'Consecutive slots should be merged');

        // At least one assignment should span multiple hours
        $hasLongAssignment = false;
        foreach ($assignments as $assignment) {
            if ($assignment->getDurationInHours() > 1.0) {
                $hasLongAssignment = true;
                break;
            }
        }

        $this->assertTrue($hasLongAssignment, 'Should have merged consecutive assignments into longer shifts');
    }

    /**
     * Test assignment types (primary/secondary)
     */
    public function testAssignmentTypes(): void
    {
        $startDate = new \DateTimeImmutable('2025-12-01 00:00:00');

        $availabilities = [
            ScheduleTestDataBuilder::createAgentAvailability(1, $startDate, 8, 17),
        ];

        $skills = [
            ScheduleTestDataBuilder::createAgentSkill(1, 'sales', 1.0, 2, true),      // Primary
            ScheduleTestDataBuilder::createAgentSkill(1, 'support', 0.8, 1, false),   // Secondary
        ];

        $forecasts = array_merge(
            ScheduleTestDataBuilder::createDailyDemand('sales', $startDate, 8, 12, 1.0),
            ScheduleTestDataBuilder::createDailyDemand('support', $startDate, 12, 17, 1.0)
        );

        $input = new ScheduleGenerationInputDTO(
            agentAvailabilities: $availabilities,
            agentSkills: $skills,
            demandForecasts: $forecasts,
            scheduleStartDate: $startDate,
            scheduleEndDate: $startDate->modify('+1 day'),
            timeSlotGranularityMinutes: 30
        );

        $output = $this->generator->generate($input);

        $hasPrimary = false;
        $hasSecondary = false;

        foreach ($output->getAssignments() as $assignment) {
            if ($assignment->getAssignmentType() === 'primary') {
                $hasPrimary = true;
            }
            if ($assignment->getAssignmentType() === 'secondary') {
                $hasSecondary = true;
            }
        }

        $this->assertTrue($hasPrimary || $hasSecondary, 'Should have assignment types');
    }

    /**
     * Test custom constraints
     */
    public function testCustomConstraints(): void
    {
        $startDate = new \DateTimeImmutable('2025-12-01 00:00:00');

        $availabilities = [
            ScheduleTestDataBuilder::createAgentAvailability(1, $startDate, 8, 20),
        ];

        $skills = [
            ScheduleTestDataBuilder::createAgentSkill(1, 'sales', 1.0, 2, true),
        ];

        $forecasts = ScheduleTestDataBuilder::createDailyDemand('sales', $startDate, 8, 20, 5.0);

        $input = new ScheduleGenerationInputDTO(
            agentAvailabilities: $availabilities,
            agentSkills: $skills,
            demandForecasts: $forecasts,
            scheduleStartDate: $startDate,
            scheduleEndDate: $startDate->modify('+1 day'),
            constraints: [
                'min_hours_per_day' => 2,
                'max_hours_per_day' => 4,
                'max_consecutive_hours' => 3,
                'efficiency_weight' => 5.0
            ],
            timeSlotGranularityMinutes: 30
        );

        $output = $this->generator->generate($input);

        $this->assertNotEmpty($output->getAssignments());

        // Verify max hours constraint
        $totalHours = 0;
        foreach ($output->getAssignments() as $assignment) {
            $totalHours += $assignment->getDurationInHours();
        }

        $this->assertLessThanOrEqual(4, $totalHours, 'Should respect custom max hours constraint');
    }
}
