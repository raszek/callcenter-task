<?php

namespace App\Tests\Controller;

use App\Entity\Agent;
use App\Entity\AgentSkill;
use App\Entity\Queue;
use App\Repository\AgentSkillRepository;
use Symfony\Component\HttpFoundation\Response;

class AgentSkillControllerTest extends WebTestCase
{
    public function testIndex(): void
    {
        $client = static::createClient();

        $agent = $this->createAgent('John', 'Doe');
        $queue = $this->createQueue('sales', 'Sales Team');
        $this->createAgentSkill($agent, $queue, 1.0, 5, true);
        $this->createAgentSkill($agent, $queue, 0.8, 3, false);

        $client->request('GET', '/api/agents/' . $agent->getId() . '/skills');

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/json');

        $response = json_decode($client->getResponse()->getContent(), true);

        $this->assertIsArray($response);
        $this->assertCount(2, $response);
        $this->assertEquals($agent->getId(), $response[0]['agentId']);
        $this->assertEquals('John', $response[0]['agentFirstName']);
        $this->assertEquals('Doe', $response[0]['agentLastName']);
        $this->assertEquals($queue->getId(), $response[0]['queueId']);
        $this->assertEquals('sales', $response[0]['queueName']);
        $this->assertEquals('Sales Team', $response[0]['queueDisplayName']);
        $this->assertEquals(1.0, $response[0]['efficiencyCoefficient']);
        $this->assertEquals(5, $response[0]['skillLevel']);
        $this->assertTrue($response[0]['isPrimary']);
    }

    public function testIndexReturnsEmptyArrayWhenNoSkills(): void
    {
        $client = static::createClient();

        $agent = $this->createAgent('John', 'Doe');

        $client->request('GET', '/api/agents/' . $agent->getId() . '/skills');

        $this->assertResponseIsSuccessful();

        $response = json_decode($client->getResponse()->getContent(), true);

        $this->assertIsArray($response);
        $this->assertEmpty($response);
    }

    public function testIndexReturns404WhenAgentNotFound(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/agents/999999/skills');

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);

        $response = json_decode($client->getResponse()->getContent(), true);

        $this->assertArrayHasKey('error', $response);
        $this->assertEquals('Agent not found', $response['error']);
    }

    public function testIndexOnlyReturnsSkillsForSpecificAgent(): void
    {
        $client = static::createClient();

        $agent1 = $this->createAgent('John', 'Doe');
        $agent2 = $this->createAgent('Jane', 'Smith');
        $queue = $this->createQueue('sales', 'Sales Team');

        $this->createAgentSkill($agent1, $queue, 1.0, 5, true);
        $this->createAgentSkill($agent2, $queue, 0.8, 3, false);

        $client->request('GET', '/api/agents/' . $agent1->getId() . '/skills');

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
        $queue = $this->createQueue('sales', 'Sales Team');
        $skill = $this->createAgentSkill($agent, $queue, 1.0, 5, true);

        $client->request('GET', '/api/agents/' . $agent->getId() . '/skills/' . $skill->getId());

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/json');

        $response = json_decode($client->getResponse()->getContent(), true);

        $this->assertEquals($skill->getId(), $response['id']);
        $this->assertEquals($agent->getId(), $response['agentId']);
        $this->assertEquals('John', $response['agentFirstName']);
        $this->assertEquals('Doe', $response['agentLastName']);
        $this->assertEquals($queue->getId(), $response['queueId']);
        $this->assertEquals('sales', $response['queueName']);
        $this->assertEquals('Sales Team', $response['queueDisplayName']);
        $this->assertEquals(1.0, $response['efficiencyCoefficient']);
        $this->assertEquals(5, $response['skillLevel']);
        $this->assertTrue($response['isPrimary']);
    }

    public function testShowReturns404WhenAgentNotFound(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/agents/999999/skills/1');

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);

        $response = json_decode($client->getResponse()->getContent(), true);

        $this->assertArrayHasKey('error', $response);
        $this->assertEquals('Agent not found', $response['error']);
    }

    public function testShowReturns404WhenSkillNotFound(): void
    {
        $client = static::createClient();

        $agent = $this->createAgent('John', 'Doe');

        $client->request('GET', '/api/agents/' . $agent->getId() . '/skills/999999');

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);

        $response = json_decode($client->getResponse()->getContent(), true);

        $this->assertArrayHasKey('error', $response);
        $this->assertEquals('Agent skill not found', $response['error']);
    }

    public function testShowReturns404WhenSkillDoesNotBelongToAgent(): void
    {
        $client = static::createClient();

        $agent1 = $this->createAgent('John', 'Doe');
        $agent2 = $this->createAgent('Jane', 'Smith');
        $queue = $this->createQueue('sales', 'Sales Team');
        $skill = $this->createAgentSkill($agent2, $queue, 1.0, 5, true);

        $client->request('GET', '/api/agents/' . $agent1->getId() . '/skills/' . $skill->getId());

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);

        $response = json_decode($client->getResponse()->getContent(), true);

        $this->assertArrayHasKey('error', $response);
        $this->assertEquals('Agent skill does not belong to this agent', $response['error']);
    }

    public function testCreate(): void
    {
        $client = static::createClient();

        $agent = $this->createAgent('John', 'Doe');
        $queue = $this->createQueue('sales', 'Sales Team');

        $client->request(
            'POST',
            '/api/agents/' . $agent->getId() . '/skills',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'queueId' => $queue->getId(),
                'efficiencyCoefficient' => 1.0,
                'skillLevel' => 5,
                'isPrimary' => true
            ])
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $this->assertResponseHeaderSame('content-type', 'application/json');

        $response = json_decode($client->getResponse()->getContent(), true);

        $this->assertArrayHasKey('id', $response);
        $this->assertEquals($agent->getId(), $response['agentId']);
        $this->assertEquals('John', $response['agentFirstName']);
        $this->assertEquals('Doe', $response['agentLastName']);
        $this->assertEquals($queue->getId(), $response['queueId']);
        $this->assertEquals('sales', $response['queueName']);
        $this->assertEquals('Sales Team', $response['queueDisplayName']);
        $this->assertEquals(1.0, $response['efficiencyCoefficient']);
        $this->assertEquals(5, $response['skillLevel']);
        $this->assertTrue($response['isPrimary']);

        // Verify skill was persisted to database
        $repository = $this->getService(AgentSkillRepository::class);
        $skill = $repository->find($response['id']);

        $this->assertNotNull($skill);
        $this->assertEquals($agent->getId(), $skill->getAgent()->getId());
        $this->assertEquals($queue->getId(), $skill->getQueue()->getId());
    }

    public function testCreateReturns404WhenAgentNotFound(): void
    {
        $client = static::createClient();

        $queue = $this->createQueue('sales', 'Sales Team');

        $client->request(
            'POST',
            '/api/agents/999999/skills',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'queueId' => $queue->getId(),
                'efficiencyCoefficient' => 1.0,
                'skillLevel' => 5,
                'isPrimary' => true
            ])
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);

        $response = json_decode($client->getResponse()->getContent(), true);

        $this->assertArrayHasKey('error', $response);
        $this->assertEquals('Agent not found', $response['error']);
    }

    public function testCreateReturns404WhenQueueNotFound(): void
    {
        $client = static::createClient();

        $agent = $this->createAgent('John', 'Doe');

        $client->request(
            'POST',
            '/api/agents/' . $agent->getId() . '/skills',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'queueId' => 999999,
                'efficiencyCoefficient' => 1.0,
                'skillLevel' => 5,
                'isPrimary' => true
            ])
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);

        $response = json_decode($client->getResponse()->getContent(), true);

        $this->assertArrayHasKey('error', $response);
        $this->assertEquals('Queue not found', $response['error']);
    }

    public function testCreateValidatesQueueIdIsRequired(): void
    {
        $client = static::createClient();

        $agent = $this->createAgent('John', 'Doe');

        $client->request(
            'POST',
            '/api/agents/' . $agent->getId() . '/skills',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'efficiencyCoefficient' => 1.0,
                'skillLevel' => 5,
                'isPrimary' => true
            ])
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testCreateValidatesEfficiencyCoefficientIsRequired(): void
    {
        $client = static::createClient();

        $agent = $this->createAgent('John', 'Doe');
        $queue = $this->createQueue('sales', 'Sales Team');

        $client->request(
            'POST',
            '/api/agents/' . $agent->getId() . '/skills',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'queueId' => $queue->getId(),
                'skillLevel' => 5,
                'isPrimary' => true
            ])
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testCreateValidatesSkillLevelIsRequired(): void
    {
        $client = static::createClient();

        $agent = $this->createAgent('John', 'Doe');
        $queue = $this->createQueue('sales', 'Sales Team');

        $client->request(
            'POST',
            '/api/agents/' . $agent->getId() . '/skills',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'queueId' => $queue->getId(),
                'efficiencyCoefficient' => 1.0,
                'isPrimary' => true
            ])
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testCreateValidatesIsPrimaryIsRequired(): void
    {
        $client = static::createClient();

        $agent = $this->createAgent('John', 'Doe');
        $queue = $this->createQueue('sales', 'Sales Team');

        $client->request(
            'POST',
            '/api/agents/' . $agent->getId() . '/skills',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'queueId' => $queue->getId(),
                'efficiencyCoefficient' => 1.0,
                'skillLevel' => 5
            ])
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testUpdate(): void
    {
        $client = static::createClient();

        $agent = $this->createAgent('John', 'Doe');
        $queue1 = $this->createQueue('sales', 'Sales Team');
        $queue2 = $this->createQueue('support', 'Support Team');
        $skill = $this->createAgentSkill($agent, $queue1, 1.0, 5, true);

        $client->request(
            'PUT',
            '/api/agents/' . $agent->getId() . '/skills/' . $skill->getId(),
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'queueId' => $queue2->getId(),
                'efficiencyCoefficient' => 0.8,
                'skillLevel' => 3,
                'isPrimary' => false
            ])
        );

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/json');

        $response = json_decode($client->getResponse()->getContent(), true);

        $this->assertEquals($skill->getId(), $response['id']);
        $this->assertEquals($agent->getId(), $response['agentId']);
        $this->assertEquals('John', $response['agentFirstName']);
        $this->assertEquals('Doe', $response['agentLastName']);
        $this->assertEquals($queue2->getId(), $response['queueId']);
        $this->assertEquals('support', $response['queueName']);
        $this->assertEquals('Support Team', $response['queueDisplayName']);
        $this->assertEquals(0.8, $response['efficiencyCoefficient']);
        $this->assertEquals(3, $response['skillLevel']);
        $this->assertFalse($response['isPrimary']);

        // Verify skill was updated in database
        $repository = $this->getService(AgentSkillRepository::class);
        $updatedSkill = $repository->find($skill->getId());

        $this->assertEquals($agent->getId(), $updatedSkill->getAgent()->getId());
        $this->assertEquals($queue2->getId(), $updatedSkill->getQueue()->getId());
        $this->assertEquals(0.8, $updatedSkill->getEfficiencyCoefficient());
        $this->assertEquals(3, $updatedSkill->getSkillLevel());
        $this->assertFalse($updatedSkill->isPrimary());
    }

    public function testUpdateReturns404WhenAgentNotFound(): void
    {
        $client = static::createClient();

        $queue = $this->createQueue('sales', 'Sales Team');

        $client->request(
            'PUT',
            '/api/agents/999999/skills/1',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'queueId' => $queue->getId(),
                'efficiencyCoefficient' => 1.0,
                'skillLevel' => 5,
                'isPrimary' => true
            ])
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);

        $response = json_decode($client->getResponse()->getContent(), true);

        $this->assertArrayHasKey('error', $response);
        $this->assertEquals('Agent not found', $response['error']);
    }

    public function testUpdateReturns404WhenSkillNotFound(): void
    {
        $client = static::createClient();

        $agent = $this->createAgent('John', 'Doe');
        $queue = $this->createQueue('sales', 'Sales Team');

        $client->request(
            'PUT',
            '/api/agents/' . $agent->getId() . '/skills/999999',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'queueId' => $queue->getId(),
                'efficiencyCoefficient' => 1.0,
                'skillLevel' => 5,
                'isPrimary' => true
            ])
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);

        $response = json_decode($client->getResponse()->getContent(), true);

        $this->assertArrayHasKey('error', $response);
        $this->assertEquals('Agent skill not found', $response['error']);
    }

    public function testUpdateReturns404WhenSkillDoesNotBelongToAgent(): void
    {
        $client = static::createClient();

        $agent1 = $this->createAgent('John', 'Doe');
        $agent2 = $this->createAgent('Jane', 'Smith');
        $queue = $this->createQueue('sales', 'Sales Team');
        $skill = $this->createAgentSkill($agent2, $queue, 1.0, 5, true);

        $client->request(
            'PUT',
            '/api/agents/' . $agent1->getId() . '/skills/' . $skill->getId(),
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'queueId' => $queue->getId(),
                'efficiencyCoefficient' => 1.0,
                'skillLevel' => 5,
                'isPrimary' => true
            ])
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);

        $response = json_decode($client->getResponse()->getContent(), true);

        $this->assertArrayHasKey('error', $response);
        $this->assertEquals('Agent skill does not belong to this agent', $response['error']);
    }

    public function testUpdateReturns404WhenQueueNotFound(): void
    {
        $client = static::createClient();

        $agent = $this->createAgent('John', 'Doe');
        $queue = $this->createQueue('sales', 'Sales Team');
        $skill = $this->createAgentSkill($agent, $queue, 1.0, 5, true);

        $client->request(
            'PUT',
            '/api/agents/' . $agent->getId() . '/skills/' . $skill->getId(),
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'queueId' => 999999,
                'efficiencyCoefficient' => 1.0,
                'skillLevel' => 5,
                'isPrimary' => true
            ])
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);

        $response = json_decode($client->getResponse()->getContent(), true);

        $this->assertArrayHasKey('error', $response);
        $this->assertEquals('Queue not found', $response['error']);
    }

    public function testDelete(): void
    {
        $client = static::createClient();

        $agent = $this->createAgent('John', 'Doe');
        $queue = $this->createQueue('sales', 'Sales Team');
        $skill = $this->createAgentSkill($agent, $queue, 1.0, 5, true);
        $skillId = $skill->getId();

        $client->request('DELETE', '/api/agents/' . $agent->getId() . '/skills/' . $skillId);

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        // Verify skill was deleted from database
        $repository = $this->getService(AgentSkillRepository::class);
        $deletedSkill = $repository->find($skillId);

        $this->assertNull($deletedSkill);
    }

    public function testDeleteReturns404WhenAgentNotFound(): void
    {
        $client = static::createClient();

        $client->request('DELETE', '/api/agents/999999/skills/1');

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);

        $response = json_decode($client->getResponse()->getContent(), true);

        $this->assertArrayHasKey('error', $response);
        $this->assertEquals('Agent not found', $response['error']);
    }

    public function testDeleteReturns404WhenSkillNotFound(): void
    {
        $client = static::createClient();

        $agent = $this->createAgent('John', 'Doe');

        $client->request('DELETE', '/api/agents/' . $agent->getId() . '/skills/999999');

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);

        $response = json_decode($client->getResponse()->getContent(), true);

        $this->assertArrayHasKey('error', $response);
        $this->assertEquals('Agent skill not found', $response['error']);
    }

    public function testDeleteReturns404WhenSkillDoesNotBelongToAgent(): void
    {
        $client = static::createClient();

        $agent1 = $this->createAgent('John', 'Doe');
        $agent2 = $this->createAgent('Jane', 'Smith');
        $queue = $this->createQueue('sales', 'Sales Team');
        $skill = $this->createAgentSkill($agent2, $queue, 1.0, 5, true);

        $client->request('DELETE', '/api/agents/' . $agent1->getId() . '/skills/' . $skill->getId());

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);

        $response = json_decode($client->getResponse()->getContent(), true);

        $this->assertArrayHasKey('error', $response);
        $this->assertEquals('Agent skill does not belong to this agent', $response['error']);
    }

    private function createAgent(string $firstName, string $lastName): Agent
    {
        $agent = new Agent($firstName, $lastName);

        $entityManager = $this->getService('doctrine.orm.entity_manager');
        $entityManager->persist($agent);
        $entityManager->flush();

        return $agent;
    }

    private function createQueue(string $name, string $displayName): Queue
    {
        $queue = new Queue();
        $queue->setName($name);
        $queue->setDisplayName($displayName);

        $entityManager = $this->getService('doctrine.orm.entity_manager');
        $entityManager->persist($queue);
        $entityManager->flush();

        return $queue;
    }

    private function createAgentSkill(
        Agent $agent,
        Queue $queue,
        float $efficiencyCoefficient,
        int $skillLevel,
        bool $isPrimary
    ): AgentSkill {
        $skill = new AgentSkill(
            efficiencyCoefficient: $efficiencyCoefficient,
            skillLevel: $skillLevel,
            isPrimary: $isPrimary,
            agent: $agent,
            queue: $queue
        );

        $entityManager = $this->getService('doctrine.orm.entity_manager');
        $entityManager->persist($skill);
        $entityManager->flush();

        return $skill;
    }
}
