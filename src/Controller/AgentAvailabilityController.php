<?php

namespace App\Controller;

use App\DTO\AgentAvailabilityResponse;
use App\DTO\CreateAgentAvailabilityRequest;
use App\DTO\UpdateAgentAvailabilityRequest;
use App\Entity\AgentAvailability;
use App\Repository\AgentAvailabilityRepository;
use App\Repository\AgentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/agents/{agentId}/availabilities')]
class AgentAvailabilityController extends AbstractController
{
    public function __construct(
        private readonly AgentAvailabilityRepository $agentAvailabilityRepository,
        private readonly AgentRepository $agentRepository,
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    #[Route('', methods: ['GET'])]
    public function index(int $agentId): JsonResponse
    {
        $agent = $this->agentRepository->find($agentId);

        if (!$agent) {
            return $this->json(['error' => 'Agent not found'], Response::HTTP_NOT_FOUND);
        }

        $agentAvailabilities = $this->agentAvailabilityRepository->findBy(['agent' => $agent]);

        $response = array_map(
            fn(AgentAvailability $agentAvailability) => AgentAvailabilityResponse::fromEntity($agentAvailability),
            $agentAvailabilities
        );

        return $this->json($response);
    }

    #[Route('/{id}', methods: ['GET'])]
    public function show(int $agentId, int $id): JsonResponse
    {
        $agent = $this->agentRepository->find($agentId);

        if (!$agent) {
            return $this->json(['error' => 'Agent not found'], Response::HTTP_NOT_FOUND);
        }

        $agentAvailability = $this->agentAvailabilityRepository->find($id);

        if (!$agentAvailability) {
            return $this->json(['error' => 'Agent availability not found'], Response::HTTP_NOT_FOUND);
        }

        if ($agentAvailability->getAgent()->getId() !== $agentId) {
            return $this->json(['error' => 'Agent availability does not belong to this agent'], Response::HTTP_NOT_FOUND);
        }

        return $this->json(AgentAvailabilityResponse::fromEntity($agentAvailability));
    }

    #[Route('', methods: ['POST'])]
    public function create(
        int $agentId,
        #[MapRequestPayload] CreateAgentAvailabilityRequest $request
    ): JsonResponse {
        $agent = $this->agentRepository->find($agentId);

        if (!$agent) {
            return $this->json(['error' => 'Agent not found'], Response::HTTP_NOT_FOUND);
        }

        try {
            $startTime = new \DateTimeImmutable($request->startTime);
            $endTime = new \DateTimeImmutable($request->endTime);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Invalid datetime format'], Response::HTTP_BAD_REQUEST);
        }

        if ($startTime >= $endTime) {
            return $this->json(['error' => 'Start time must be before end time'], Response::HTTP_BAD_REQUEST);
        }

        $agentAvailability = new AgentAvailability(
            startTime: $startTime,
            endTime: $endTime,
            isAvailable: $request->isAvailable,
            agent: $agent
        );

        $this->entityManager->persist($agentAvailability);
        $this->entityManager->flush();

        return $this->json(
            AgentAvailabilityResponse::fromEntity($agentAvailability),
            Response::HTTP_CREATED
        );
    }

    #[Route('/{id}', methods: ['PUT'])]
    public function update(
        int $agentId,
        int $id,
        #[MapRequestPayload] UpdateAgentAvailabilityRequest $request
    ): JsonResponse {
        $agent = $this->agentRepository->find($agentId);

        if (!$agent) {
            return $this->json(['error' => 'Agent not found'], Response::HTTP_NOT_FOUND);
        }

        $agentAvailability = $this->agentAvailabilityRepository->find($id);

        if (!$agentAvailability) {
            return $this->json(['error' => 'Agent availability not found'], Response::HTTP_NOT_FOUND);
        }

        if ($agentAvailability->getAgent()->getId() !== $agentId) {
            return $this->json(['error' => 'Agent availability does not belong to this agent'], Response::HTTP_NOT_FOUND);
        }

        try {
            $startTime = new \DateTimeImmutable($request->startTime);
            $endTime = new \DateTimeImmutable($request->endTime);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Invalid datetime format'], Response::HTTP_BAD_REQUEST);
        }

        if ($startTime >= $endTime) {
            return $this->json(['error' => 'Start time must be before end time'], Response::HTTP_BAD_REQUEST);
        }

        $agentAvailability->setStartTime($startTime);
        $agentAvailability->setEndTime($endTime);
        $agentAvailability->setIsAvailable($request->isAvailable);

        $this->entityManager->flush();

        return $this->json(AgentAvailabilityResponse::fromEntity($agentAvailability));
    }

    #[Route('/{id}', methods: ['DELETE'])]
    public function delete(int $agentId, int $id): JsonResponse
    {
        $agent = $this->agentRepository->find($agentId);

        if (!$agent) {
            return $this->json(['error' => 'Agent not found'], Response::HTTP_NOT_FOUND);
        }

        $agentAvailability = $this->agentAvailabilityRepository->find($id);

        if (!$agentAvailability) {
            return $this->json(['error' => 'Agent availability not found'], Response::HTTP_NOT_FOUND);
        }

        if ($agentAvailability->getAgent()->getId() !== $agentId) {
            return $this->json(['error' => 'Agent availability does not belong to this agent'], Response::HTTP_NOT_FOUND);
        }

        $this->entityManager->remove($agentAvailability);
        $this->entityManager->flush();

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }
}
