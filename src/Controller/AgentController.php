<?php

namespace App\Controller;

use App\DTO\AgentResponse;
use App\DTO\CreateAgentRequest;
use App\DTO\UpdateAgentRequest;
use App\Entity\Agent;
use App\Repository\AgentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/agents')]
class AgentController extends AbstractController
{
    public function __construct(
        private readonly AgentRepository $agentRepository,
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    #[Route('', methods: ['GET'])]
    public function index(): JsonResponse
    {
        $agents = $this->agentRepository->findAll();

        $response = array_map(
            fn(Agent $agent) => AgentResponse::fromEntity($agent),
            $agents
        );

        return $this->json($response);
    }

    #[Route('/{id}', methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        $agent = $this->agentRepository->find($id);

        if (!$agent) {
            return $this->json(['error' => 'Agent not found'], Response::HTTP_NOT_FOUND);
        }

        return $this->json(AgentResponse::fromEntity($agent));
    }

    #[Route('', methods: ['POST'])]
    public function create(
        #[MapRequestPayload] CreateAgentRequest $request
    ): JsonResponse {
        $agent = new Agent(
            firstName: $request->firstName,
            lastName: $request->lastName
        );

        $this->entityManager->persist($agent);
        $this->entityManager->flush();

        return $this->json(
            AgentResponse::fromEntity($agent),
            Response::HTTP_CREATED
        );
    }

    #[Route('/{id}', methods: ['PUT'])]
    public function update(
        int $id,
        #[MapRequestPayload] UpdateAgentRequest $request
    ): JsonResponse {
        $agent = $this->agentRepository->find($id);

        if (!$agent) {
            return $this->json(['error' => 'Agent not found'], Response::HTTP_NOT_FOUND);
        }

        $agent->setFirstName($request->firstName);
        $agent->setLastName($request->lastName);

        $this->entityManager->flush();

        return $this->json(AgentResponse::fromEntity($agent));
    }

    #[Route('/{id}', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $agent = $this->agentRepository->find($id);

        if (!$agent) {
            return $this->json(['error' => 'Agent not found'], Response::HTTP_NOT_FOUND);
        }

        $this->entityManager->remove($agent);
        $this->entityManager->flush();

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }
}
