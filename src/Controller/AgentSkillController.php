<?php

namespace App\Controller;

use App\DTO\AgentSkillResponse;
use App\DTO\CreateAgentSkillRequest;
use App\DTO\UpdateAgentSkillRequest;
use App\Entity\AgentSkill;
use App\Repository\AgentRepository;
use App\Repository\AgentSkillRepository;
use App\Repository\QueueRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/agents/{agentId}/skills')]
class AgentSkillController extends AbstractController
{
    public function __construct(
        private readonly AgentSkillRepository $agentSkillRepository,
        private readonly AgentRepository $agentRepository,
        private readonly QueueRepository $queueRepository,
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

        $agentSkills = $this->agentSkillRepository->findBy(['agent' => $agent]);

        $response = array_map(
            fn(AgentSkill $agentSkill) => AgentSkillResponse::fromEntity($agentSkill),
            $agentSkills
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

        $agentSkill = $this->agentSkillRepository->find($id);

        if (!$agentSkill) {
            return $this->json(['error' => 'Agent skill not found'], Response::HTTP_NOT_FOUND);
        }

        if ($agentSkill->getAgent()->getId() !== $agentId) {
            return $this->json(['error' => 'Agent skill does not belong to this agent'], Response::HTTP_NOT_FOUND);
        }

        return $this->json(AgentSkillResponse::fromEntity($agentSkill));
    }

    #[Route('', methods: ['POST'])]
    public function create(
        int $agentId,
        #[MapRequestPayload] CreateAgentSkillRequest $request
    ): JsonResponse {
        $agent = $this->agentRepository->find($agentId);

        if (!$agent) {
            return $this->json(['error' => 'Agent not found'], Response::HTTP_NOT_FOUND);
        }

        $queue = $this->queueRepository->find($request->queueId);

        if (!$queue) {
            return $this->json(['error' => 'Queue not found'], Response::HTTP_NOT_FOUND);
        }

        $agentSkill = new AgentSkill(
            efficiencyCoefficient: $request->efficiencyCoefficient,
            skillLevel: $request->skillLevel,
            isPrimary: $request->isPrimary,
            agent: $agent,
            queue: $queue
        );

        $this->entityManager->persist($agentSkill);
        $this->entityManager->flush();

        return $this->json(
            AgentSkillResponse::fromEntity($agentSkill),
            Response::HTTP_CREATED
        );
    }

    #[Route('/{id}', methods: ['PUT'])]
    public function update(
        int $agentId,
        int $id,
        #[MapRequestPayload] UpdateAgentSkillRequest $request
    ): JsonResponse {
        $agent = $this->agentRepository->find($agentId);

        if (!$agent) {
            return $this->json(['error' => 'Agent not found'], Response::HTTP_NOT_FOUND);
        }

        $agentSkill = $this->agentSkillRepository->find($id);

        if (!$agentSkill) {
            return $this->json(['error' => 'Agent skill not found'], Response::HTTP_NOT_FOUND);
        }

        if ($agentSkill->getAgent()->getId() !== $agentId) {
            return $this->json(['error' => 'Agent skill does not belong to this agent'], Response::HTTP_NOT_FOUND);
        }

        $queue = $this->queueRepository->find($request->queueId);

        if (!$queue) {
            return $this->json(['error' => 'Queue not found'], Response::HTTP_NOT_FOUND);
        }

        $agentSkill->setQueue($queue);
        $agentSkill->setEfficiencyCoefficient($request->efficiencyCoefficient);
        $agentSkill->setSkillLevel($request->skillLevel);
        $agentSkill->setIsPrimary($request->isPrimary);

        $this->entityManager->flush();

        return $this->json(AgentSkillResponse::fromEntity($agentSkill));
    }

    #[Route('/{id}', methods: ['DELETE'])]
    public function delete(int $agentId, int $id): JsonResponse
    {
        $agent = $this->agentRepository->find($agentId);

        if (!$agent) {
            return $this->json(['error' => 'Agent not found'], Response::HTTP_NOT_FOUND);
        }

        $agentSkill = $this->agentSkillRepository->find($id);

        if (!$agentSkill) {
            return $this->json(['error' => 'Agent skill not found'], Response::HTTP_NOT_FOUND);
        }

        if ($agentSkill->getAgent()->getId() !== $agentId) {
            return $this->json(['error' => 'Agent skill does not belong to this agent'], Response::HTTP_NOT_FOUND);
        }

        $this->entityManager->remove($agentSkill);
        $this->entityManager->flush();

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }
}
