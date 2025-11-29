<?php

namespace App\Controller;

use App\DTO\HistoricalCallDataResponse;
use App\Entity\HistoricalCallData;
use App\Repository\HistoricalCallDataRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/historical-call-data')]
class HistoricalCallDataController extends AbstractController
{
    public function __construct(
        private readonly HistoricalCallDataRepository $historicalCallDataRepository
    ) {
    }

    /**
     * Get all historical call data with optional filtering
     */
    #[Route('', methods: ['GET'])]
    public function index(Request $request): JsonResponse
    {
        $qb = $this->historicalCallDataRepository->createQueryBuilder('h')
            ->join('h.queue', 'q')
            ->orderBy('h.datetime', 'DESC');

        // Filter by queue name
        if ($request->query->has('queueName')) {
            $qb->andWhere('q.name = :queueName')
                ->setParameter('queueName', $request->query->get('queueName'));
        }

        // Filter by start date
        if ($request->query->has('startDate')) {
            try {
                $startDate = new \DateTimeImmutable($request->query->get('startDate'));
                $qb->andWhere('h.datetime >= :startDate')
                    ->setParameter('startDate', $startDate);
            } catch (\Exception $e) {
                return $this->json(
                    ['error' => 'Invalid startDate format'],
                    Response::HTTP_BAD_REQUEST
                );
            }
        }

        // Filter by end date
        if ($request->query->has('endDate')) {
            try {
                $endDate = new \DateTimeImmutable($request->query->get('endDate'));
                $qb->andWhere('h.datetime <= :endDate')
                    ->setParameter('endDate', $endDate);
            } catch (\Exception $e) {
                return $this->json(
                    ['error' => 'Invalid endDate format'],
                    Response::HTTP_BAD_REQUEST
                );
            }
        }

        // Limit results
        $limit = (int) $request->query->get('limit', 100);
        if ($limit > 0) {
            $qb->setMaxResults(min($limit, 1000));
        }

        $historicalData = $qb->getQuery()->getResult();

        $response = array_map(
            fn(HistoricalCallData $data) => HistoricalCallDataResponse::fromEntity($data),
            $historicalData
        );

        return $this->json($response);
    }

    /**
     * Get single historical call data record
     */
    #[Route('/{id}', methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        $data = $this->historicalCallDataRepository->find($id);

        if (!$data) {
            return $this->json(
                ['error' => 'Historical call data not found'],
                Response::HTTP_NOT_FOUND
            );
        }

        return $this->json(HistoricalCallDataResponse::fromEntity($data));
    }
}
