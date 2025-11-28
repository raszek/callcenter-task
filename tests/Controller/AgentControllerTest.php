<?php

namespace App\Tests\Controller;

use App\Entity\Agent;
use App\Repository\AgentRepository;
use Symfony\Component\HttpFoundation\Response;

class AgentControllerTest extends WebTestCase
{
    private const API_BASE_URL = '/api/agents';

    public function testIndex(): void
    {
        $client = static::createClient();

        // Create test agents
        $this->createAgent('John', 'Doe');
        $this->createAgent('Jane', 'Smith');

        $client->request('GET', self::API_BASE_URL);

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/json');

        $response = json_decode($client->getResponse()->getContent(), true);

        $this->assertIsArray($response);
        $this->assertCount(2, $response);
        $this->assertEquals('John', $response[0]['firstName']);
        $this->assertEquals('Doe', $response[0]['lastName']);
        $this->assertEquals('Jane', $response[1]['firstName']);
        $this->assertEquals('Smith', $response[1]['lastName']);
    }

    public function testIndexReturnsEmptyArrayWhenNoAgents(): void
    {
        $client = static::createClient();

        $client->request('GET', self::API_BASE_URL);

        $this->assertResponseIsSuccessful();

        $response = json_decode($client->getResponse()->getContent(), true);

        $this->assertIsArray($response);
        $this->assertEmpty($response);
    }

    public function testShow(): void
    {
        $client = static::createClient();

        $agent = $this->createAgent('John', 'Doe');

        $client->request('GET', self::API_BASE_URL . '/' . $agent->getId());

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/json');

        $response = json_decode($client->getResponse()->getContent(), true);

        $this->assertEquals($agent->getId(), $response['id']);
        $this->assertEquals('John', $response['firstName']);
        $this->assertEquals('Doe', $response['lastName']);
    }

    public function testShowReturns404WhenAgentNotFound(): void
    {
        $client = static::createClient();

        $client->request('GET', self::API_BASE_URL . '/999999');

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);

        $response = json_decode($client->getResponse()->getContent(), true);

        $this->assertArrayHasKey('error', $response);
        $this->assertEquals('Agent not found', $response['error']);
    }

    public function testCreate(): void
    {
        $client = static::createClient();

        $client->request(
            'POST',
            self::API_BASE_URL,
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'firstName' => 'John',
                'lastName' => 'Doe'
            ])
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $this->assertResponseHeaderSame('content-type', 'application/json');

        $response = json_decode($client->getResponse()->getContent(), true);

        $this->assertArrayHasKey('id', $response);
        $this->assertEquals('John', $response['firstName']);
        $this->assertEquals('Doe', $response['lastName']);

        // Verify agent was persisted to database
        $agentRepository = $this->getService(AgentRepository::class);
        $agent = $agentRepository->find($response['id']);

        $this->assertNotNull($agent);
        $this->assertEquals('John', $agent->getFirstName());
        $this->assertEquals('Doe', $agent->getLastName());
    }

    public function testCreateValidatesFirstNameIsRequired(): void
    {
        $client = static::createClient();

        $client->request(
            'POST',
            self::API_BASE_URL,
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'lastName' => 'Doe'
            ])
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testCreateValidatesLastNameIsRequired(): void
    {
        $client = static::createClient();

        $client->request(
            'POST',
            self::API_BASE_URL,
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'firstName' => 'John'
            ])
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testCreateValidatesFirstNameIsNotBlank(): void
    {
        $client = static::createClient();

        $client->request(
            'POST',
            self::API_BASE_URL,
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'firstName' => '',
                'lastName' => 'Doe'
            ])
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testCreateValidatesLastNameIsNotBlank(): void
    {
        $client = static::createClient();

        $client->request(
            'POST',
            self::API_BASE_URL,
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'firstName' => 'John',
                'lastName' => ''
            ])
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testCreateValidatesFirstNameMaxLength(): void
    {
        $client = static::createClient();

        $client->request(
            'POST',
            self::API_BASE_URL,
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'firstName' => str_repeat('a', 256),
                'lastName' => 'Doe'
            ])
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testCreateValidatesLastNameMaxLength(): void
    {
        $client = static::createClient();

        $client->request(
            'POST',
            self::API_BASE_URL,
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'firstName' => 'John',
                'lastName' => str_repeat('a', 256)
            ])
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testUpdate(): void
    {
        $client = static::createClient();

        $agent = $this->createAgent('John', 'Doe');

        $client->request(
            'PUT',
            self::API_BASE_URL . '/' . $agent->getId(),
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'firstName' => 'Jane',
                'lastName' => 'Smith'
            ])
        );

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/json');

        $response = json_decode($client->getResponse()->getContent(), true);

        $this->assertEquals($agent->getId(), $response['id']);
        $this->assertEquals('Jane', $response['firstName']);
        $this->assertEquals('Smith', $response['lastName']);

        // Verify agent was updated in database
        $agentRepository = $this->getService(AgentRepository::class);
        $updatedAgent = $agentRepository->find($agent->getId());

        $this->assertEquals('Jane', $updatedAgent->getFirstName());
        $this->assertEquals('Smith', $updatedAgent->getLastName());
    }

    public function testUpdateReturns404WhenAgentNotFound(): void
    {
        $client = static::createClient();

        $client->request(
            'PUT',
            self::API_BASE_URL . '/999999',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'firstName' => 'Jane',
                'lastName' => 'Smith'
            ])
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);

        $response = json_decode($client->getResponse()->getContent(), true);

        $this->assertArrayHasKey('error', $response);
        $this->assertEquals('Agent not found', $response['error']);
    }

    public function testUpdateValidatesFirstNameIsRequired(): void
    {
        $client = static::createClient();

        $agent = $this->createAgent('John', 'Doe');

        $client->request(
            'PUT',
            self::API_BASE_URL . '/' . $agent->getId(),
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'lastName' => 'Smith'
            ])
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testUpdateValidatesLastNameIsRequired(): void
    {
        $client = static::createClient();

        $agent = $this->createAgent('John', 'Doe');

        $client->request(
            'PUT',
            self::API_BASE_URL . '/' . $agent->getId(),
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'firstName' => 'Jane'
            ])
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testDelete(): void
    {
        $client = static::createClient();

        $agent = $this->createAgent('John', 'Doe');
        $agentId = $agent->getId();

        $client->request('DELETE', self::API_BASE_URL . '/' . $agentId);

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        // Verify agent was deleted from database
        $agentRepository = $this->getService(AgentRepository::class);
        $deletedAgent = $agentRepository->find($agentId);

        $this->assertNull($deletedAgent);
    }

    public function testDeleteReturns404WhenAgentNotFound(): void
    {
        $client = static::createClient();

        $client->request('DELETE', self::API_BASE_URL . '/999999');

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);

        $response = json_decode($client->getResponse()->getContent(), true);

        $this->assertArrayHasKey('error', $response);
        $this->assertEquals('Agent not found', $response['error']);
    }

    private function createAgent(string $firstName, string $lastName): Agent
    {
        $agent = new Agent($firstName, $lastName);

        $entityManager = $this->getService('doctrine.orm.entity_manager');
        $entityManager->persist($agent);
        $entityManager->flush();

        return $agent;
    }
}
