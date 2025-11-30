<?php

namespace App\Controller;

use App\DTO\CreateHistoricalCallDataRequest;
use App\DTO\GenerateHistoricalCallDataRequest;
use App\DTO\HistoricalCallDataResponse;
use App\Entity\HistoricalCallData;
use App\Service\HistoricalCallDataService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/historical-call-data')]
class HistoricalCallDataController extends AbstractController
{
    public function __construct(
        private readonly HistoricalCallDataService $historicalCallDataService
    ) {
    }

    /**
     * Get all historical call data with optional filtering
     */
    #[Route('', methods: ['GET'])]
    public function index(Request $request): JsonResponse
    {
        try {
            $filters = [
                'queueName' => $request->query->get('queueName'),
                'startDate' => $request->query->get('startDate'),
                'endDate' => $request->query->get('endDate'),
                'limit' => $request->query->get('limit', 100),
            ];

            $historicalData = $this->historicalCallDataService->getFilteredHistoricalCallData($filters);

            $response = array_map(
                fn(HistoricalCallData $data) => HistoricalCallDataResponse::fromEntity($data),
                $historicalData
            );

            return $this->json($response);
        } catch (\InvalidArgumentException $e) {
            return $this->json(
                ['error' => $e->getMessage()],
                Response::HTTP_BAD_REQUEST
            );
        }
    }

    /**
     * Get single historical call data record
     */
    #[Route('/{id}', methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        try {
            $data = $this->historicalCallDataService->getHistoricalCallDataById($id);

            return $this->json(HistoricalCallDataResponse::fromEntity($data));
        } catch (\InvalidArgumentException $e) {
            return $this->json(
                ['error' => $e->getMessage()],
                Response::HTTP_NOT_FOUND
            );
        }
    }

    /**
     * Add historical call data entries up to present day
     */
    #[Route('', methods: ['POST'])]
    public function create(
        #[MapRequestPayload] CreateHistoricalCallDataRequest $request
    ): JsonResponse {
        try {
            $response = $this->historicalCallDataService->createHistoricalCallData($request);
            $statusCode = empty($response['created']) ? Response::HTTP_BAD_REQUEST : Response::HTTP_CREATED;

            return $this->json($response, $statusCode);
        } catch (\InvalidArgumentException $e) {
            return $this->json(
                ['error' => $e->getMessage()],
                Response::HTTP_NOT_FOUND
            );
        }
    }

    /**
     * Generate random historical call data for all queues
     */
    #[Route('/generate', methods: ['POST'])]
    public function generate(
        #[MapRequestPayload] GenerateHistoricalCallDataRequest $request
    ): JsonResponse {
        try {
            $response = $this->historicalCallDataService->generateRandomData($request);

            return $this->json($response, Response::HTTP_CREATED);
        } catch (\RuntimeException $e) {
            return $this->json(
                ['error' => $e->getMessage()],
                Response::HTTP_NOT_FOUND
            );
        }
    }
}
