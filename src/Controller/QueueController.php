<?php

namespace App\Controller;

use App\DTO\QueueResponse;
use App\Entity\Queue;
use App\Repository\QueueRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/queues')]
class QueueController extends AbstractController
{
    public function __construct(
        private readonly QueueRepository $queueRepository
    ) {
    }

    #[Route('', methods: ['GET'])]
    public function index(): JsonResponse
    {
        $queues = $this->queueRepository->findAll();

        $response = array_map(
            fn(Queue $queue) => QueueResponse::fromEntity($queue),
            $queues
        );

        return $this->json($response);
    }
}
