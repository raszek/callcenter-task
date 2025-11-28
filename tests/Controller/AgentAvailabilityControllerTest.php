<?php

namespace App\Tests\Controller;

use App\Entity\Agent;
use App\Entity\AgentAvailability;
use App\Repository\AgentAvailabilityRepository;
use DateTimeImmutable;
use Symfony\Component\HttpFoundation\Response;

class AgentAvailabilityControllerTest extends WebTestCase
{
    public function testIndex(): void
    {
        $client = static::createClient();

        $agent = $this->createAgent('John', 'Doe');
        $this->createAgentAvailability($agent, '2025-12-01 09:00:00', '2025-12-01 17:00:00', true);
        $this->createAgentAvailability($agent, '2025-12-02 09:00:00', '2025-12-02 17:00:00', false);

        $client->request('GET', '/api/agents/' . $agent->getId() . '/availabilities');

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/json');

        $response = json_decode($client->getResponse()->getContent(), true);

        $this->assertIsArray($response);
        $this->assertCount(2, $response);
        $this->assertEquals($agent->getId(), $response[0]['agentId']);
        $this->assertEquals('John', $response[0]['agentFirstName']);
        $this->assertEquals('Doe', $response[0]['agentLastName']);
        $this->assertEquals('2025-12-01 09:00:00', $response[0]['startTime']);
        $this->assertEquals('2025-12-01 17:00:00', $response[0]['endTime']);
        $this->assertTrue($response[0]['isAvailable']);
    }

    public function testIndexReturnsEmptyArrayWhenNoAvailabilities(): void
    {
        $client = static::createClient();

        $agent = $this->createAgent('John', 'Doe');

        $client->request('GET', '/api/agents/' . $agent->getId() . '/availabilities');

        $this->assertResponseIsSuccessful();

        $response = json_decode($client->getResponse()->getContent(), true);

        $this->assertIsArray($response);
        $this->assertEmpty($response);
    }

    public function testIndexReturns404WhenAgentNotFound(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/agents/999999/availabilities');

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);

        $response = json_decode($client->getResponse()->getContent(), true);

        $this->assertArrayHasKey('error', $response);
        $this->assertEquals('Agent not found', $response['error']);
    }

    public function testIndexOnlyReturnsAvailabilitiesForSpecificAgent(): void
    {
        $client = static::createClient();

        $agent1 = $this->createAgent('John', 'Doe');
        $agent2 = $this->createAgent('Jane', 'Smith');

        $this->createAgentAvailability($agent1, '2025-12-01 09:00:00', '2025-12-01 17:00:00', true);
        $this->createAgentAvailability($agent2, '2025-12-02 09:00:00', '2025-12-02 17:00:00', false);

        $client->request('GET', '/api/agents/' . $agent1->getId() . '/availabilities');

        $this->assertResponseIsSuccessful();

        $response = json_decode($client->getResponse()->getContent(), true);

        $this->assertIsArray($response);
        $this->assertCount(1, $response);
        $this->assertEquals($agent1->getId(), $response[0]['agentId']);
    }

    public function testShow(): void
    {
        $client = static::createClient();

        $agent = $this->createAgent('John', 'Doe');
        $availability = $this->createAgentAvailability($agent, '2025-12-01 09:00:00', '2025-12-01 17:00:00', true);

        $client->request('GET', '/api/agents/' . $agent->getId() . '/availabilities/' . $availability->getId());

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/json');

        $response = json_decode($client->getResponse()->getContent(), true);

        $this->assertEquals($availability->getId(), $response['id']);
        $this->assertEquals($agent->getId(), $response['agentId']);
        $this->assertEquals('John', $response['agentFirstName']);
        $this->assertEquals('Doe', $response['agentLastName']);
        $this->assertEquals('2025-12-01 09:00:00', $response['startTime']);
        $this->assertEquals('2025-12-01 17:00:00', $response['endTime']);
        $this->assertTrue($response['isAvailable']);
    }

    public function testShowReturns404WhenAgentNotFound(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/agents/999999/availabilities/1');

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);

        $response = json_decode($client->getResponse()->getContent(), true);

        $this->assertArrayHasKey('error', $response);
        $this->assertEquals('Agent not found', $response['error']);
    }

    public function testShowReturns404WhenAvailabilityNotFound(): void
    {
        $client = static::createClient();

        $agent = $this->createAgent('John', 'Doe');

        $client->request('GET', '/api/agents/' . $agent->getId() . '/availabilities/999999');

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);

        $response = json_decode($client->getResponse()->getContent(), true);

        $this->assertArrayHasKey('error', $response);
        $this->assertEquals('Agent availability not found', $response['error']);
    }

    public function testShowReturns404WhenAvailabilityDoesNotBelongToAgent(): void
    {
        $client = static::createClient();

        $agent1 = $this->createAgent('John', 'Doe');
        $agent2 = $this->createAgent('Jane', 'Smith');
        $availability = $this->createAgentAvailability($agent2, '2025-12-01 09:00:00', '2025-12-01 17:00:00', true);

        $client->request('GET', '/api/agents/' . $agent1->getId() . '/availabilities/' . $availability->getId());

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);

        $response = json_decode($client->getResponse()->getContent(), true);

        $this->assertArrayHasKey('error', $response);
        $this->assertEquals('Agent availability does not belong to this agent', $response['error']);
    }

    public function testCreate(): void
    {
        $client = static::createClient();

        $agent = $this->createAgent('John', 'Doe');

        $client->request(
            'POST',
            '/api/agents/' . $agent->getId() . '/availabilities',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'startTime' => '2025-12-01 09:00:00',
                'endTime' => '2025-12-01 17:00:00',
                'isAvailable' => true
            ])
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $this->assertResponseHeaderSame('content-type', 'application/json');

        $response = json_decode($client->getResponse()->getContent(), true);

        $this->assertArrayHasKey('id', $response);
        $this->assertEquals($agent->getId(), $response['agentId']);
        $this->assertEquals('John', $response['agentFirstName']);
        $this->assertEquals('Doe', $response['agentLastName']);
        $this->assertEquals('2025-12-01 09:00:00', $response['startTime']);
        $this->assertEquals('2025-12-01 17:00:00', $response['endTime']);
        $this->assertTrue($response['isAvailable']);

        // Verify availability was persisted to database
        $repository = $this->getService(AgentAvailabilityRepository::class);
        $availability = $repository->find($response['id']);

        $this->assertNotNull($availability);
        $this->assertEquals($agent->getId(), $availability->getAgent()->getId());
    }

    public function testCreateReturns404WhenAgentNotFound(): void
    {
        $client = static::createClient();

        $client->request(
            'POST',
            '/api/agents/999999/availabilities',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'startTime' => '2025-12-01 09:00:00',
                'endTime' => '2025-12-01 17:00:00',
                'isAvailable' => true
            ])
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);

        $response = json_decode($client->getResponse()->getContent(), true);

        $this->assertArrayHasKey('error', $response);
        $this->assertEquals('Agent not found', $response['error']);
    }

    public function testCreateValidatesStartTimeIsRequired(): void
    {
        $client = static::createClient();

        $agent = $this->createAgent('John', 'Doe');

        $client->request(
            'POST',
            '/api/agents/' . $agent->getId() . '/availabilities',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'endTime' => '2025-12-01 17:00:00',
                'isAvailable' => true
            ])
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testCreateValidatesEndTimeIsRequired(): void
    {
        $client = static::createClient();

        $agent = $this->createAgent('John', 'Doe');

        $client->request(
            'POST',
            '/api/agents/' . $agent->getId() . '/availabilities',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'startTime' => '2025-12-01 09:00:00',
                'isAvailable' => true
            ])
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testCreateValidatesIsAvailableIsRequired(): void
    {
        $client = static::createClient();

        $agent = $this->createAgent('John', 'Doe');

        $client->request(
            'POST',
            '/api/agents/' . $agent->getId() . '/availabilities',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'startTime' => '2025-12-01 09:00:00',
                'endTime' => '2025-12-01 17:00:00'
            ])
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testCreateValidatesStartTimeBeforeEndTime(): void
    {
        $client = static::createClient();

        $agent = $this->createAgent('John', 'Doe');

        $client->request(
            'POST',
            '/api/agents/' . $agent->getId() . '/availabilities',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'startTime' => '2025-12-01 17:00:00',
                'endTime' => '2025-12-01 09:00:00',
                'isAvailable' => true
            ])
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);

        $response = json_decode($client->getResponse()->getContent(), true);

        $this->assertArrayHasKey('error', $response);
        $this->assertEquals('Start time must be before end time', $response['error']);
    }

    public function testCreateValidatesInvalidDateTimeFormat(): void
    {
        $client = static::createClient();

        $agent = $this->createAgent('John', 'Doe');

        $client->request(
            'POST',
            '/api/agents/' . $agent->getId() . '/availabilities',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'startTime' => 'invalid-datetime',
                'endTime' => '2025-12-01 17:00:00',
                'isAvailable' => true
            ])
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);

        $response = json_decode($client->getResponse()->getContent(), true);

        $this->assertArrayHasKey('detail', $response);
        $this->assertEquals('startTime: This value is not a valid datetime.', $response['detail']);
    }

    public function testUpdate(): void
    {
        $client = static::createClient();

        $agent = $this->createAgent('John', 'Doe');
        $availability = $this->createAgentAvailability($agent, '2025-12-01 09:00:00', '2025-12-01 17:00:00', true);

        $client->request(
            'PUT',
            '/api/agents/' . $agent->getId() . '/availabilities/' . $availability->getId(),
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'startTime' => '2025-12-02 10:00:00',
                'endTime' => '2025-12-02 18:00:00',
                'isAvailable' => false
            ])
        );

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/json');

        $response = json_decode($client->getResponse()->getContent(), true);

        $this->assertEquals($availability->getId(), $response['id']);
        $this->assertEquals($agent->getId(), $response['agentId']);
        $this->assertEquals('2025-12-02 10:00:00', $response['startTime']);
        $this->assertEquals('2025-12-02 18:00:00', $response['endTime']);
        $this->assertFalse($response['isAvailable']);

        // Verify availability was updated in database
        $repository = $this->getService(AgentAvailabilityRepository::class);
        $updatedAvailability = $repository->find($availability->getId());

        $this->assertEquals('2025-12-02 10:00:00', $updatedAvailability->getStartTime()->format('Y-m-d H:i:s'));
        $this->assertEquals('2025-12-02 18:00:00', $updatedAvailability->getEndTime()->format('Y-m-d H:i:s'));
        $this->assertFalse($updatedAvailability->isAvailable());
    }

    public function testUpdateReturns404WhenAgentNotFound(): void
    {
        $client = static::createClient();

        $client->request(
            'PUT',
            '/api/agents/999999/availabilities/1',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'startTime' => '2025-12-01 09:00:00',
                'endTime' => '2025-12-01 17:00:00',
                'isAvailable' => true
            ])
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);

        $response = json_decode($client->getResponse()->getContent(), true);

        $this->assertArrayHasKey('error', $response);
        $this->assertEquals('Agent not found', $response['error']);
    }

    public function testUpdateReturns404WhenAvailabilityNotFound(): void
    {
        $client = static::createClient();

        $agent = $this->createAgent('John', 'Doe');

        $client->request(
            'PUT',
            '/api/agents/' . $agent->getId() . '/availabilities/999999',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'startTime' => '2025-12-01 09:00:00',
                'endTime' => '2025-12-01 17:00:00',
                'isAvailable' => true
            ])
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);

        $response = json_decode($client->getResponse()->getContent(), true);

        $this->assertArrayHasKey('error', $response);
        $this->assertEquals('Agent availability not found', $response['error']);
    }

    public function testUpdateReturns404WhenAvailabilityDoesNotBelongToAgent(): void
    {
        $client = static::createClient();

        $agent1 = $this->createAgent('John', 'Doe');
        $agent2 = $this->createAgent('Jane', 'Smith');
        $availability = $this->createAgentAvailability($agent2, '2025-12-01 09:00:00', '2025-12-01 17:00:00', true);

        $client->request(
            'PUT',
            '/api/agents/' . $agent1->getId() . '/availabilities/' . $availability->getId(),
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'startTime' => '2025-12-01 09:00:00',
                'endTime' => '2025-12-01 17:00:00',
                'isAvailable' => true
            ])
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);

        $response = json_decode($client->getResponse()->getContent(), true);

        $this->assertArrayHasKey('error', $response);
        $this->assertEquals('Agent availability does not belong to this agent', $response['error']);
    }

    public function testUpdateValidatesStartTimeBeforeEndTime(): void
    {
        $client = static::createClient();

        $agent = $this->createAgent('John', 'Doe');
        $availability = $this->createAgentAvailability($agent, '2025-12-01 09:00:00', '2025-12-01 17:00:00', true);

        $client->request(
            'PUT',
            '/api/agents/' . $agent->getId() . '/availabilities/' . $availability->getId(),
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'startTime' => '2025-12-01 17:00:00',
                'endTime' => '2025-12-01 09:00:00',
                'isAvailable' => true
            ])
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);

        $response = json_decode($client->getResponse()->getContent(), true);

        $this->assertArrayHasKey('error', $response);
        $this->assertEquals('Start time must be before end time', $response['error']);
    }

    public function testDelete(): void
    {
        $client = static::createClient();

        $agent = $this->createAgent('John', 'Doe');
        $availability = $this->createAgentAvailability($agent, '2025-12-01 09:00:00', '2025-12-01 17:00:00', true);
        $availabilityId = $availability->getId();

        $client->request('DELETE', '/api/agents/' . $agent->getId() . '/availabilities/' . $availabilityId);

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        // Verify availability was deleted from database
        $repository = $this->getService(AgentAvailabilityRepository::class);
        $deletedAvailability = $repository->find($availabilityId);

        $this->assertNull($deletedAvailability);
    }

    public function testDeleteReturns404WhenAgentNotFound(): void
    {
        $client = static::createClient();

        $client->request('DELETE', '/api/agents/999999/availabilities/1');

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);

        $response = json_decode($client->getResponse()->getContent(), true);

        $this->assertArrayHasKey('error', $response);
        $this->assertEquals('Agent not found', $response['error']);
    }

    public function testDeleteReturns404WhenAvailabilityNotFound(): void
    {
        $client = static::createClient();

        $agent = $this->createAgent('John', 'Doe');

        $client->request('DELETE', '/api/agents/' . $agent->getId() . '/availabilities/999999');

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);

        $response = json_decode($client->getResponse()->getContent(), true);

        $this->assertArrayHasKey('error', $response);
        $this->assertEquals('Agent availability not found', $response['error']);
    }

    public function testDeleteReturns404WhenAvailabilityDoesNotBelongToAgent(): void
    {
        $client = static::createClient();

        $agent1 = $this->createAgent('John', 'Doe');
        $agent2 = $this->createAgent('Jane', 'Smith');
        $availability = $this->createAgentAvailability($agent2, '2025-12-01 09:00:00', '2025-12-01 17:00:00', true);

        $client->request('DELETE', '/api/agents/' . $agent1->getId() . '/availabilities/' . $availability->getId());

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);

        $response = json_decode($client->getResponse()->getContent(), true);

        $this->assertArrayHasKey('error', $response);
        $this->assertEquals('Agent availability does not belong to this agent', $response['error']);
    }

    private function createAgent(string $firstName, string $lastName): Agent
    {
        $agent = new Agent($firstName, $lastName);

        $entityManager = $this->getService('doctrine.orm.entity_manager');
        $entityManager->persist($agent);
        $entityManager->flush();

        return $agent;
    }

    private function createAgentAvailability(
        Agent $agent,
        string $startTime,
        string $endTime,
        bool $isAvailable
    ): AgentAvailability {
        $availability = new AgentAvailability(
            startTime: new DateTimeImmutable($startTime),
            endTime: new DateTimeImmutable($endTime),
            isAvailable: $isAvailable,
            agent: $agent,
        );

        $entityManager = $this->getService('doctrine.orm.entity_manager');
        $entityManager->persist($availability);
        $entityManager->flush();

        return $availability;
    }
}
