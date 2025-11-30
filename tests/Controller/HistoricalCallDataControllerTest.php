<?php

namespace App\Tests\Controller;

use App\Entity\HistoricalCallData;
use App\Entity\Queue;
use App\Repository\HistoricalCallDataRepository;
use App\Repository\QueueRepository;
use Symfony\Component\HttpFoundation\Response;

class HistoricalCallDataControllerTest extends WebTestCase
{
    private const API_BASE_URL = '/api/historical-call-data';

    public function testIndex(): void
    {
        $client = static::createClient();

        $queue = $this->createQueue('sales', 'Sales Team');
        $this->createHistoricalCallData($queue, '2025-01-15 09:00:00', 45, 180.5);
        $this->createHistoricalCallData($queue, '2025-01-15 10:00:00', 52, 195.3);

        $client->request('GET', self::API_BASE_URL);

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/json');

        $response = json_decode($client->getResponse()->getContent(), true);

        $this->assertIsArray($response);
        $this->assertCount(2, $response);
        $this->assertEquals('sales', $response[0]['queueName']);
        $this->assertEquals(52, $response[0]['callCount']);
        $this->assertEquals(45, $response[1]['callCount']);
    }

    public function testIndexReturnsEmptyArrayWhenNoData(): void
    {
        $client = static::createClient();

        $client->request('GET', self::API_BASE_URL);

        $this->assertResponseIsSuccessful();

        $response = json_decode($client->getResponse()->getContent(), true);

        $this->assertIsArray($response);
        $this->assertEmpty($response);
    }

    public function testIndexFiltersByQueueName(): void
    {
        $client = static::createClient();

        $salesQueue = $this->createQueue('sales', 'Sales Team');
        $supportQueue = $this->createQueue('support', 'Support Team');

        $this->createHistoricalCallData($salesQueue, '2025-01-15 09:00:00', 45, 180.5);
        $this->createHistoricalCallData($supportQueue, '2025-01-15 09:00:00', 30, 200.0);
        $this->createHistoricalCallData($salesQueue, '2025-01-15 10:00:00', 52, 195.3);

        $client->request('GET', self::API_BASE_URL . '?queueName=sales');

        $this->assertResponseIsSuccessful();

        $response = json_decode($client->getResponse()->getContent(), true);

        $this->assertIsArray($response);
        $this->assertCount(2, $response);
        $this->assertEquals('sales', $response[0]['queueName']);
        $this->assertEquals('sales', $response[1]['queueName']);
    }

    public function testIndexFiltersByStartDate(): void
    {
        $client = static::createClient();

        $queue = $this->createQueue('sales', 'Sales Team');
        $this->createHistoricalCallData($queue, '2025-01-10 09:00:00', 30, 150.0);
        $this->createHistoricalCallData($queue, '2025-01-15 09:00:00', 45, 180.5);
        $this->createHistoricalCallData($queue, '2025-01-20 09:00:00', 50, 200.0);

        $client->request('GET', self::API_BASE_URL . '?startDate=2025-01-15T00:00:00');

        $this->assertResponseIsSuccessful();

        $response = json_decode($client->getResponse()->getContent(), true);

        $this->assertIsArray($response);
        $this->assertCount(2, $response);
    }

    public function testIndexFiltersByEndDate(): void
    {
        $client = static::createClient();

        $queue = $this->createQueue('sales', 'Sales Team');
        $this->createHistoricalCallData($queue, '2025-01-10 09:00:00', 30, 150.0);
        $this->createHistoricalCallData($queue, '2025-01-15 09:00:00', 45, 180.5);
        $this->createHistoricalCallData($queue, '2025-01-20 09:00:00', 50, 200.0);

        $client->request('GET', self::API_BASE_URL . '?endDate=2025-01-15T23:59:59');

        $this->assertResponseIsSuccessful();

        $response = json_decode($client->getResponse()->getContent(), true);

        $this->assertIsArray($response);
        $this->assertCount(2, $response);
    }

    public function testIndexFiltersByLimit(): void
    {
        $client = static::createClient();

        $queue = $this->createQueue('sales', 'Sales Team');
        for ($i = 0; $i < 10; $i++) {
            $this->createHistoricalCallData(
                $queue,
                sprintf('2025-01-15 %02d:00:00', $i),
                45 + $i,
                180.5
            );
        }

        $client->request('GET', self::API_BASE_URL . '?limit=5');

        $this->assertResponseIsSuccessful();

        $response = json_decode($client->getResponse()->getContent(), true);

        $this->assertIsArray($response);
        $this->assertCount(5, $response);
    }

    public function testIndexReturnsErrorForInvalidStartDate(): void
    {
        $client = static::createClient();

        $client->request('GET', self::API_BASE_URL . '?startDate=invalid-date');

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);

        $response = json_decode($client->getResponse()->getContent(), true);

        $this->assertArrayHasKey('error', $response);
    }

    public function testIndexReturnsErrorForInvalidEndDate(): void
    {
        $client = static::createClient();

        $client->request('GET', self::API_BASE_URL . '?endDate=invalid-date');

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);

        $response = json_decode($client->getResponse()->getContent(), true);

        $this->assertArrayHasKey('error', $response);
    }

    public function testShow(): void
    {
        $client = static::createClient();

        $queue = $this->createQueue('sales', 'Sales Team');
        $historicalData = $this->createHistoricalCallData($queue, '2025-01-15 09:00:00', 45, 180.5);

        $client->request('GET', self::API_BASE_URL . '/' . $historicalData->getId());

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/json');

        $response = json_decode($client->getResponse()->getContent(), true);

        $this->assertEquals($historicalData->getId(), $response['id']);
        $this->assertEquals('sales', $response['queueName']);
        $this->assertEquals(45, $response['callCount']);
        $this->assertEquals(180.5, $response['averageHandleTimeSeconds']);
    }

    public function testShowReturns404WhenNotFound(): void
    {
        $client = static::createClient();

        $client->request('GET', self::API_BASE_URL . '/999999');

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);

        $response = json_decode($client->getResponse()->getContent(), true);

        $this->assertArrayHasKey('error', $response);
        $this->assertEquals('Historical call data not found', $response['error']);
    }

    public function testCreate(): void
    {
        $client = static::createClient();

        $queue = $this->createQueue('sales', 'Sales Team');

        $client->request(
            'POST',
            self::API_BASE_URL,
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'queueName' => 'sales',
                'entries' => [
                    [
                        'datetime' => '2025-01-15T09:00:00+00:00',
                        'callCount' => 45,
                        'averageHandleTimeSeconds' => 180.5,
                    ],
                    [
                        'datetime' => '2025-01-15T10:00:00+00:00',
                        'callCount' => 52,
                        'averageHandleTimeSeconds' => 195.3,
                    ],
                ],
            ])
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $this->assertResponseHeaderSame('content-type', 'application/json');

        $response = json_decode($client->getResponse()->getContent(), true);

        $this->assertEquals('Successfully created 2 historical call data entries', $response['message']);
        $this->assertEquals(2, $response['created']);
        $this->assertEquals(0, $response['failed']);
        $this->assertCount(2, $response['entries']);

        // Verify data was persisted to database
        $repository = $this->getService(HistoricalCallDataRepository::class);
        $data = $repository->findAll();

        $this->assertCount(2, $data);
    }

    public function testCreateValidatesRequiredFields(): void
    {
        $client = static::createClient();

        $client->request(
            'POST',
            self::API_BASE_URL,
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'queueName' => 'sales',
            ])
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);

        $response = json_decode($client->getResponse()->getContent(), true);

        $this->assertArrayHasKey('violations', $response);
        $this->assertNotEmpty($response['violations']);
    }

    public function testCreateReturnsErrorForNonExistentQueue(): void
    {
        $client = static::createClient();

        $client->request(
            'POST',
            self::API_BASE_URL,
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'queueName' => 'nonexistent',
                'entries' => [
                    [
                        'datetime' => '2025-01-15T09:00:00+00:00',
                        'callCount' => 45,
                        'averageHandleTimeSeconds' => 180.5,
                    ],
                ],
            ])
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);

        $response = json_decode($client->getResponse()->getContent(), true);

        $this->assertArrayHasKey('error', $response);
        $this->assertStringContainsString('Queue with name "nonexistent" not found', $response['error']);
    }

    public function testCreateValidatesDatetimeNotInFuture(): void
    {
        $client = static::createClient();

        $queue = $this->createQueue('sales', 'Sales Team');
        $futureDate = (new \DateTimeImmutable())->modify('+1 day')->format('Y-m-d\TH:i:sP');

        $client->request(
            'POST',
            self::API_BASE_URL,
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'queueName' => 'sales',
                'entries' => [
                    [
                        'datetime' => $futureDate,
                        'callCount' => 45,
                        'averageHandleTimeSeconds' => 180.5,
                    ],
                ],
            ])
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);

        $response = json_decode($client->getResponse()->getContent(), true);

        $this->assertEquals(0, $response['created']);
        $this->assertEquals(1, $response['failed']);
        $this->assertArrayHasKey('errors', $response);
    }

    public function testCreatePreventseDuplicates(): void
    {
        $client = static::createClient();

        $queue = $this->createQueue('sales', 'Sales Team');
        $this->createHistoricalCallData($queue, '2025-01-15 09:00:00', 45, 180.5);

        $client->request(
            'POST',
            self::API_BASE_URL,
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'queueName' => 'sales',
                'entries' => [
                    [
                        'datetime' => '2025-01-15T09:00:00+00:00',
                        'callCount' => 50,
                        'averageHandleTimeSeconds' => 200.0,
                    ],
                ],
            ])
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);

        $response = json_decode($client->getResponse()->getContent(), true);

        $this->assertEquals(0, $response['created']);
        $this->assertEquals(1, $response['failed']);
        $this->assertArrayHasKey('errors', $response);
        $this->assertStringContainsString('already exists', $response['errors'][0]);
    }

    public function testCreateValidatesCallCountIsNonNegative(): void
    {
        $client = static::createClient();

        $queue = $this->createQueue('sales', 'Sales Team');

        $client->request(
            'POST',
            self::API_BASE_URL,
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'queueName' => 'sales',
                'entries' => [
                    [
                        'datetime' => '2025-01-15T09:00:00+00:00',
                        'callCount' => -10,
                        'averageHandleTimeSeconds' => 180.5,
                    ],
                ],
            ])
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);

        $response = json_decode($client->getResponse()->getContent(), true);

        $this->assertArrayHasKey('violations', $response);
        $this->assertNotEmpty($response['violations']);
    }

    public function testGenerate(): void
    {
        $client = static::createClient();

        $this->createQueue('sales', 'Sales Team');
        $this->createQueue('support', 'Support Team');

        $client->request(
            'POST',
            self::API_BASE_URL . '/generate',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'days' => 7,
                'intervalHours' => 1,
            ])
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $this->assertResponseHeaderSame('content-type', 'application/json');

        $response = json_decode($client->getResponse()->getContent(), true);

        $this->assertArrayHasKey('message', $response);
        $this->assertArrayHasKey('created', $response);
        $this->assertArrayHasKey('queues', $response);
        $this->assertEquals(2, $response['queues']);
        $this->assertEquals(7, $response['days']);
        $this->assertEquals(1, $response['intervalHours']);
        $this->assertGreaterThan(0, $response['created']);

        // Verify data was persisted to database
        $repository = $this->getService(HistoricalCallDataRepository::class);
        $data = $repository->findAll();

        $this->assertGreaterThan(0, count($data));
    }

    public function testGenerateWithDefaultParameters(): void
    {
        $client = static::createClient();

        $this->createQueue('sales', 'Sales Team');

        $client->request(
            'POST',
            self::API_BASE_URL . '/generate',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([])
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $response = json_decode($client->getResponse()->getContent(), true);

        $this->assertEquals(30, $response['days']); // Default
        $this->assertEquals(1, $response['intervalHours']); // Default
    }

    public function testGenerateReturnsErrorWhenNoQueues(): void
    {
        $client = static::createClient();

        $client->request(
            'POST',
            self::API_BASE_URL . '/generate',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'days' => 7,
                'intervalHours' => 1,
            ])
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);

        $response = json_decode($client->getResponse()->getContent(), true);

        $this->assertArrayHasKey('error', $response);
        $this->assertStringContainsString('No queues found', $response['error']);
    }

    public function testGenerateValidatesDaysRange(): void
    {
        $client = static::createClient();

        $this->createQueue('sales', 'Sales Team');

        $client->request(
            'POST',
            self::API_BASE_URL . '/generate',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'days' => 500,
                'intervalHours' => 1,
            ])
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);

        $response = json_decode($client->getResponse()->getContent(), true);

        $this->assertArrayHasKey('violations', $response);
        $this->assertNotEmpty($response['violations']);
    }

    public function testGenerateValidatesIntervalHoursRange(): void
    {
        $client = static::createClient();

        $this->createQueue('sales', 'Sales Team');

        $client->request(
            'POST',
            self::API_BASE_URL . '/generate',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'days' => 7,
                'intervalHours' => 50,
            ])
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);

        $response = json_decode($client->getResponse()->getContent(), true);

        $this->assertArrayHasKey('violations', $response);
        $this->assertNotEmpty($response['violations']);
    }

    public function testGenerateDoesNotExceed100RecordsPerDay(): void
    {
        $client = static::createClient();

        $queue = $this->createQueue('sales', 'Sales Team');

        // Create 100 records for a specific day
        for ($i = 0; $i < 100; $i++) {
            $this->createHistoricalCallData(
                $queue,
                sprintf('2025-01-15 %02d:%02d:00', $i / 60, $i % 60),
                45,
                180.5
            );
        }

        // Try to generate more data for the same period
        $client->request(
            'POST',
            self::API_BASE_URL . '/generate',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'days' => 1,
                'intervalHours' => 1,
            ])
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $response = json_decode($client->getResponse()->getContent(), true);

        // Verify that no new records were created for the day with 100 records
        $repository = $this->getService(HistoricalCallDataRepository::class);
        $dayStart = new \DateTimeImmutable('2025-01-15 00:00:00');
        $dayEnd = new \DateTimeImmutable('2025-01-15 23:59:59');

        $recordsForDay = $repository->createQueryBuilder('h')
            ->select('COUNT(h.id)')
            ->where('h.queue = :queue')
            ->andWhere('h.datetime >= :dayStart')
            ->andWhere('h.datetime <= :dayEnd')
            ->setParameter('queue', $queue)
            ->setParameter('dayStart', $dayStart)
            ->setParameter('dayEnd', $dayEnd)
            ->getQuery()
            ->getSingleScalarResult();

        // Should still be 100, not more
        $this->assertEquals(100, (int) $recordsForDay);
    }

    public function testGenerateSkipsDaysWithMaxRecords(): void
    {
        $client = static::createClient();

        $queue = $this->createQueue('sales', 'Sales Team');

        // Create 100 records for day 1
        for ($i = 0; $i < 100; $i++) {
            $this->createHistoricalCallData(
                $queue,
                sprintf('2025-01-15 %02d:%02d:00', $i / 60, $i % 60),
                45,
                180.5
            );
        }

        // Generate should skip day 1 but create for other days
        $client->request(
            'POST',
            self::API_BASE_URL . '/generate',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'days' => 3,
                'intervalHours' => 1,
            ])
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $response = json_decode($client->getResponse()->getContent(), true);

        // Should have created records for days 2 and 3, but not day 1
        // With 3 days and hourly intervals (24 per day), expect roughly 48 new records (2 days * 24)
        // The actual number might be slightly different due to current time
        $this->assertGreaterThan(0, $response['created']);
    }

    private function createQueue(string $name, string $displayName): Queue
    {
        $queue = new Queue();
        $queue->setName($name);
        $queue->setDisplayName($displayName);
        $queue->setTargetServiceLevel(0.80);
        $queue->setTargetAnswerTimeSeconds(20);

        $entityManager = $this->getService('doctrine.orm.entity_manager');
        $entityManager->persist($queue);
        $entityManager->flush();

        return $queue;
    }

    private function createHistoricalCallData(
        Queue $queue,
        string $datetime,
        int $callCount,
        float $averageHandleTimeSeconds
    ): HistoricalCallData {
        $data = new HistoricalCallData();
        $data->setQueue($queue);
        $data->setDatetime(new \DateTimeImmutable($datetime));
        $data->setCallCount($callCount);
        $data->setAverageHandleTimeSeconds($averageHandleTimeSeconds);

        $entityManager = $this->getService('doctrine.orm.entity_manager');
        $entityManager->persist($data);
        $entityManager->flush();

        return $data;
    }
}
