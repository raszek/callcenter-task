<?php

namespace App\Tests\Controller;

use App\Entity\Agent;
use App\Entity\AgentAvailability;
use App\Entity\AgentSkill;
use App\Entity\HistoricalCallData;
use App\Entity\Queue;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class ScheduleControllerTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    public function testCreateScheduleSuccess(): void
    {
        $this->createTestData();

        $this->client->request(
            'POST',
            '/api/schedules',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'scheduleStartDate' => '2025-12-01 08:00:00',
                'scheduleEndDate' => '2025-12-01 18:00:00',
                'queueNames' => ['sales'],
                'timeSlotGranularityMinutes' => 30,
                'lookbackWeeks' => 4,
                'shrinkageFactor' => 0.25,
                'targetOccupancy' => 0.85,
                'constraints' => [
                    'max_hours_per_day' => 8,
                    'max_consecutive_hours' => 6
                ]
            ])
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $this->assertResponseHeaderSame('Content-Type', 'application/json');

        $responseData = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertArrayHasKey('assignments', $responseData);
        $this->assertArrayHasKey('qualityMetrics', $responseData);
        $this->assertArrayHasKey('coverageByQueueAndHour', $responseData);
        $this->assertArrayHasKey('isFeasible', $responseData);
        $this->assertArrayHasKey('warnings', $responseData);

        $this->assertIsArray($responseData['assignments']);
        $this->assertIsArray($responseData['qualityMetrics']);
        $this->assertIsArray($responseData['warnings']);
        $this->assertIsBool($responseData['isFeasible']);
    }

    public function testCreateScheduleWithMultipleQueues(): void
    {
        $this->createTestData();

        $this->client->request(
            'POST',
            '/api/schedules',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'scheduleStartDate' => '2025-12-01 08:00:00',
                'scheduleEndDate' => '2025-12-01 12:00:00',
                'queueNames' => ['sales', 'support'],
                'timeSlotGranularityMinutes' => 30,
                'lookbackWeeks' => 4
            ])
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $responseData = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertNotEmpty($responseData['assignments']);
    }

    public function testCreateScheduleWithDifferentGranularity(): void
    {
        $this->createTestData();

        $this->client->request(
            'POST',
            '/api/schedules',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'scheduleStartDate' => '2025-12-01 08:00:00',
                'scheduleEndDate' => '2025-12-01 10:00:00',
                'queueNames' => ['sales'],
                'timeSlotGranularityMinutes' => 15,
                'lookbackWeeks' => 4
            ])
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
    }

    public function testCreateScheduleValidatesRequiredFields(): void
    {
        $this->client->request(
            'POST',
            '/api/schedules',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'scheduleStartDate' => '2025-12-01 08:00:00'
                // Missing required fields
            ])
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testCreateScheduleValidatesEmptyQueueNames(): void
    {
        $this->client->request(
            'POST',
            '/api/schedules',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'scheduleStartDate' => '2025-12-01 08:00:00',
                'scheduleEndDate' => '2025-12-08 18:00:00',
                'queueNames' => []
            ])
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testCreateScheduleValidatesInvalidDateFormat(): void
    {
        $this->client->request(
            'POST',
            '/api/schedules',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'scheduleStartDate' => 'not-a-date',
                'scheduleEndDate' => '2025-12-08 18:00:00',
                'queueNames' => ['sales']
            ])
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testCreateScheduleValidatesEndDateBeforeStartDate(): void
    {
        $this->createTestData();

        $this->client->request(
            'POST',
            '/api/schedules',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'scheduleStartDate' => '2025-12-08 08:00:00',
                'scheduleEndDate' => '2025-12-01 18:00:00',
                'queueNames' => ['sales']
            ])
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);

        $responseData = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('error', $responseData);
        $this->assertStringContainsString('end date must be after start date', $responseData['error']);
    }

    public function testCreateScheduleValidatesNegativeGranularity(): void
    {
        $this->client->request(
            'POST',
            '/api/schedules',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'scheduleStartDate' => '2025-12-01 08:00:00',
                'scheduleEndDate' => '2025-12-08 18:00:00',
                'queueNames' => ['sales'],
                'timeSlotGranularityMinutes' => -30
            ])
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testCreateScheduleValidatesInvalidShrinkageFactor(): void
    {
        $this->client->request(
            'POST',
            '/api/schedules',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'scheduleStartDate' => '2025-12-01 08:00:00',
                'scheduleEndDate' => '2025-12-08 18:00:00',
                'queueNames' => ['sales'],
                'shrinkageFactor' => 1.5
            ])
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testCreateScheduleValidatesInvalidTargetOccupancy(): void
    {
        $this->client->request(
            'POST',
            '/api/schedules',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'scheduleStartDate' => '2025-12-01 08:00:00',
                'scheduleEndDate' => '2025-12-08 18:00:00',
                'queueNames' => ['sales'],
                'targetOccupancy' => 1.2
            ])
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testCreateScheduleFailsWithNoHistoricalData(): void
    {
        // Create only queues, no historical data
        $entityManager = $this->client->getContainer()->get('doctrine')->getManager();

        $queue = new Queue();
        $queue->setName('sales');
        $queue->setDisplayName('Sales Team');
        $entityManager->persist($queue);
        $entityManager->flush();

        $this->client->request(
            'POST',
            '/api/schedules',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'scheduleStartDate' => '2025-12-01 08:00:00',
                'scheduleEndDate' => '2025-12-01 18:00:00',
                'queueNames' => ['sales']
            ])
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);

        $responseData = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('error', $responseData);
        $this->assertStringContainsString('No historical call data', $responseData['error']);
    }

    public function testCreateScheduleFailsWithNoAgentAvailabilities(): void
    {
        $entityManager = $this->client->getContainer()->get('doctrine')->getManager();

        // Create queue and historical data only
        $queue = new Queue();
        $queue->setName('sales');
        $queue->setDisplayName('Sales Team');
        $entityManager->persist($queue);

        // Add historical data
        for ($i = 0; $i < 20; $i++) {
            $historicalData = new HistoricalCallData();
            $historicalData->setQueue($queue);
            $historicalData->setDatetime(new \DateTimeImmutable('2025-11-' . (10 + $i) . ' 09:00:00'));
            $historicalData->setCallCount(50 + $i);
            $historicalData->setAverageHandleTimeSeconds(180 + ($i * 5));
            $entityManager->persist($historicalData);
        }

        $entityManager->flush();

        $this->client->request(
            'POST',
            '/api/schedules',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'scheduleStartDate' => '2025-12-01 08:00:00',
                'scheduleEndDate' => '2025-12-01 18:00:00',
                'queueNames' => ['sales']
            ])
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);

        $responseData = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('error', $responseData);
        $this->assertStringContainsString('No agent availabilities', $responseData['error']);
    }

    public function testCreateScheduleFailsWithNoAgentSkills(): void
    {
        $entityManager = $this->client->getContainer()->get('doctrine')->getManager();

        // Create queue, historical data, and agent availabilities
        $queue = new Queue();
        $queue->setName('sales');
        $queue->setDisplayName('Sales Team');
        $entityManager->persist($queue);

        $agent = new Agent('John', 'Doe');
        $entityManager->persist($agent);

        // Add historical data
        for ($i = 0; $i < 20; $i++) {
            $historicalData = new HistoricalCallData();
            $historicalData->setQueue($queue);
            $historicalData->setDatetime(new \DateTimeImmutable('2025-11-' . (10 + $i) . ' 09:00:00'));
            $historicalData->setCallCount(50 + $i);
            $historicalData->setAverageHandleTimeSeconds(180 + ($i * 5));
            $entityManager->persist($historicalData);
        }

        // Add agent availability
        $availability = new AgentAvailability(
            new \DateTimeImmutable('2025-12-01 08:00:00'),
            new \DateTimeImmutable('2025-12-01 18:00:00'),
            true,
            $agent
        );
        $entityManager->persist($availability);

        $entityManager->flush();

        $this->client->request(
            'POST',
            '/api/schedules',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'scheduleStartDate' => '2025-12-01 08:00:00',
                'scheduleEndDate' => '2025-12-01 18:00:00',
                'queueNames' => ['sales']
            ])
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);

        $responseData = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('error', $responseData);
        $this->assertStringContainsString('No agent skills', $responseData['error']);
    }

    public function testCreateScheduleWithNonExistentQueue(): void
    {
        $this->createTestData();

        $this->client->request(
            'POST',
            '/api/schedules',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'scheduleStartDate' => '2025-12-01 08:00:00',
                'scheduleEndDate' => '2025-12-01 18:00:00',
                'queueNames' => ['non_existent_queue']
            ])
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);

        $responseData = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('error', $responseData);
    }

    public function testScheduleAssignmentStructure(): void
    {
        $this->createTestData();

        $this->client->request(
            'POST',
            '/api/schedules',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'scheduleStartDate' => '2025-12-01 09:00:00',
                'scheduleEndDate' => '2025-12-01 11:00:00',
                'queueNames' => ['sales'],
                'timeSlotGranularityMinutes' => 30
            ])
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $responseData = json_decode($this->client->getResponse()->getContent(), true);

        if (!empty($responseData['assignments'])) {
            $assignment = $responseData['assignments'][0];

            $this->assertArrayHasKey('agent_id', $assignment);
            $this->assertArrayHasKey('queue_name', $assignment);
            $this->assertArrayHasKey('start_time', $assignment);
            $this->assertArrayHasKey('end_time', $assignment);
            $this->assertArrayHasKey('duration_hours', $assignment);
            $this->assertArrayHasKey('efficiency_score', $assignment);
            $this->assertArrayHasKey('assignment_type', $assignment);

            $this->assertIsInt($assignment['agent_id']);
            $this->assertIsString($assignment['queue_name']);
            $this->assertIsString($assignment['start_time']);
            $this->assertIsString($assignment['end_time']);
            $this->assertIsInt($assignment['duration_hours']);
            $this->assertIsFloat($assignment['efficiency_score']);
            $this->assertIsString($assignment['assignment_type']);
        }
    }

    public function testQualityMetricsStructure(): void
    {
        $this->createTestData();

        $this->client->request(
            'POST',
            '/api/schedules',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'scheduleStartDate' => '2025-12-01 09:00:00',
                'scheduleEndDate' => '2025-12-01 11:00:00',
                'queueNames' => ['sales']
            ])
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $responseData = json_decode($this->client->getResponse()->getContent(), true);
        $metrics = $responseData['qualityMetrics'];

        $this->assertArrayHasKey('total_assignments', $metrics);
        $this->assertArrayHasKey('total_agent_hours', $metrics);
        $this->assertArrayHasKey('average_efficiency', $metrics);
        $this->assertArrayHasKey('coverage_percentage', $metrics);
        $this->assertArrayHasKey('fairness_index', $metrics);
    }

    /**
     * Create comprehensive test data for schedule generation
     */
    private function createTestData(): void
    {
        $entityManager = $this->client->getContainer()->get('doctrine')->getManager();

        // Create queues
        $salesQueue = new Queue();
        $salesQueue->setName('sales');
        $salesQueue->setDisplayName('Sales Team');
        $entityManager->persist($salesQueue);

        $supportQueue = new Queue();
        $supportQueue->setName('support');
        $supportQueue->setDisplayName('Support Team');
        $entityManager->persist($supportQueue);

        // Create agents
        $agents = [];
        for ($i = 1; $i <= 5; $i++) {
            $agent = new Agent("Agent{$i}", "Test");
            $entityManager->persist($agent);
            $agents[] = $agent;
        }

        $entityManager->flush();

        // Create historical call data (4 weeks of data)
        $startDate = new \DateTimeImmutable('2025-11-01 08:00:00');
        for ($week = 0; $week < 4; $week++) {
            for ($day = 0; $day < 7; $day++) {
                for ($hour = 8; $hour <= 17; $hour++) {
                    for ($minute = 0; $minute < 60; $minute += 30) {
                        $datetime = $startDate
                            ->modify("+{$week} weeks")
                            ->modify("+{$day} days")
                            ->setTime($hour, $minute);

                        // Sales queue data
                        $salesData = new HistoricalCallData();
                        $salesData->setQueue($salesQueue);
                        $salesData->setDatetime($datetime);
                        $salesData->setCallCount(rand(10, 50));
                        $salesData->setAverageHandleTimeSeconds(rand(120, 300));
                        $entityManager->persist($salesData);

                        // Support queue data
                        $supportData = new HistoricalCallData();
                        $supportData->setQueue($supportQueue);
                        $supportData->setDatetime($datetime);
                        $supportData->setCallCount(rand(5, 30));
                        $supportData->setAverageHandleTimeSeconds(rand(180, 400));
                        $entityManager->persist($supportData);
                    }
                }
            }
        }

        // Create agent availabilities (December 1-7)
        foreach ($agents as $agent) {
            for ($day = 1; $day <= 7; $day++) {
                $startTime = new \DateTimeImmutable("2025-12-0{$day} 08:00:00");
                $endTime = new \DateTimeImmutable("2025-12-0{$day} 18:00:00");

                $availability = new AgentAvailability($startTime, $endTime, true, $agent);
                $entityManager->persist($availability);
            }
        }

        // Create agent skills
        foreach ($agents as $index => $agent) {
            // Each agent has sales skill
            $salesSkill = new AgentSkill(
                efficiencyCoefficient: 0.85 + ($index * 0.03),
                skillLevel: min(3, 1 + ($index % 3)),
                isPrimary: $index < 3,
                agent: $agent,
                queue: $salesQueue
            );
            $entityManager->persist($salesSkill);

            // Some agents also have support skill
            if ($index % 2 === 0) {
                $supportSkill = new AgentSkill(
                    efficiencyCoefficient: 0.80 + ($index * 0.02),
                    skillLevel: 1 + ($index % 2),
                    isPrimary: false,
                    agent: $agent,
                    queue: $supportQueue
                );
                $entityManager->persist($supportSkill);
            }
        }

        $entityManager->flush();
    }
}
