<?php

namespace App\Service;

use App\DTO\CreateHistoricalCallDataRequest;
use App\DTO\GenerateHistoricalCallDataRequest;
use App\DTO\HistoricalCallDataEntryRequest;
use App\Entity\HistoricalCallData;
use App\Entity\Queue;
use App\Repository\HistoricalCallDataRepository;
use App\Repository\QueueRepository;

class HistoricalCallDataService
{
    public function __construct(
        private readonly HistoricalCallDataRepository $historicalCallDataRepository,
        private readonly QueueRepository $queueRepository
    ) {
    }

    /**
     * Get all historical call data with optional filtering
     *
     * @param array{queueName?: string, startDate?: string, endDate?: string, limit?: int} $filters
     * @return HistoricalCallData[]
     * @throws \InvalidArgumentException
     */
    public function getFilteredHistoricalCallData(array $filters = []): array
    {
        $qb = $this->historicalCallDataRepository->createQueryBuilder('h')
            ->join('h.queue', 'q')
            ->orderBy('h.datetime', 'DESC');

        // Filter by queue name
        if (!empty($filters['queueName'])) {
            $qb->andWhere('q.name = :queueName')
                ->setParameter('queueName', $filters['queueName']);
        }

        // Filter by start date
        if (!empty($filters['startDate'])) {
            try {
                $startDate = new \DateTimeImmutable($filters['startDate']);
                $qb->andWhere('h.datetime >= :startDate')
                    ->setParameter('startDate', $startDate);
            } catch (\Exception $e) {
                throw new \InvalidArgumentException('Invalid startDate format');
            }
        }

        // Filter by end date
        if (!empty($filters['endDate'])) {
            try {
                $endDate = new \DateTimeImmutable($filters['endDate']);
                $qb->andWhere('h.datetime <= :endDate')
                    ->setParameter('endDate', $endDate);
            } catch (\Exception $e) {
                throw new \InvalidArgumentException('Invalid endDate format');
            }
        }

        // Limit results
        $limit = isset($filters['limit']) ? (int) $filters['limit'] : 100;
        if ($limit > 0) {
            $qb->setMaxResults(min($limit, 1000));
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Get single historical call data record by ID
     *
     * @throws \InvalidArgumentException
     */
    public function getHistoricalCallDataById(int $id): HistoricalCallData
    {
        $data = $this->historicalCallDataRepository->find($id);

        if (!$data) {
            throw new \InvalidArgumentException('Historical call data not found');
        }

        return $data;
    }

    /**
     * Create historical call data entries
     *
     * @return array{message: string, created: int, failed: int, entries: array, errors?: array}
     */
    public function createHistoricalCallData(CreateHistoricalCallDataRequest $request): array
    {
        // Find the queue
        $queue = $this->queueRepository->findOneByName($request->queueName);
        if (!$queue) {
            throw new \InvalidArgumentException(
                sprintf('Queue with name "%s" not found', $request->queueName)
            );
        }

        $createdEntries = [];
        $errors = [];
        $currentDate = new \DateTimeImmutable();

        foreach ($request->entries as $index => $entry) {
            try {
                $result = $this->processEntry($queue, $entry, $index, $currentDate);
                if ($result['success']) {
                    $createdEntries[] = $result['data'];
                } else {
                    $errors[] = $result['error'];
                }
            } catch (\Exception $e) {
                $errors[] = sprintf('Entry at index %d: %s', $index, $e->getMessage());
            }
        }

        // Flush all changes to database
        if (!empty($createdEntries)) {
            $entityManager = $this->historicalCallDataRepository->getEntityManager();
            $entityManager->flush();
        }

        $response = [
            'message' => sprintf('Successfully created %d historical call data entries', count($createdEntries)),
            'created' => count($createdEntries),
            'failed' => count($errors),
            'entries' => $createdEntries,
        ];

        if (!empty($errors)) {
            $response['errors'] = $errors;
        }

        return $response;
    }

    /**
     * Process a single historical call data entry
     *
     * @return array{success: bool, data?: array, error?: string}
     */
    private function processEntry(
        Queue $queue,
        HistoricalCallDataEntryRequest $entry,
        int $index,
        \DateTimeImmutable $currentDate
    ): array {
        // Parse and validate datetime
        try {
            $datetime = new \DateTimeImmutable($entry->datetime);
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => sprintf('Entry at index %d has invalid datetime format: %s', $index, $e->getMessage()),
            ];
        }

        // Check if datetime is not in the future
        if ($datetime > $currentDate) {
            return [
                'success' => false,
                'error' => sprintf(
                    'Entry at index %d has datetime in the future (%s)',
                    $index,
                    $datetime->format('Y-m-d H:i:s')
                ),
            ];
        }

        // Check if entry already exists for this queue and datetime
        $existingEntry = $this->historicalCallDataRepository->findByQueueAndDatetime($queue, $datetime);
        if ($existingEntry) {
            return [
                'success' => false,
                'error' => sprintf(
                    'Entry at index %d: Historical data already exists for queue "%s" at %s',
                    $index,
                    $queue->getName(),
                    $datetime->format('Y-m-d H:i:s')
                ),
            ];
        }

        // Create new historical call data entry
        $historicalData = new HistoricalCallData();
        $historicalData->setQueue($queue);
        $historicalData->setDatetime($datetime);
        $historicalData->setCallCount($entry->callCount);
        $historicalData->setAverageHandleTimeSeconds($entry->averageHandleTimeSeconds);

        $this->historicalCallDataRepository->save($historicalData, false);

        return [
            'success' => true,
            'data' => [
                'datetime' => $datetime->format('Y-m-d H:i:s'),
                'callCount' => $historicalData->getCallCount(),
                'averageHandleTimeSeconds' => $historicalData->getAverageHandleTimeSeconds(),
            ],
        ];
    }

    /**
     * Generate random historical call data for all queues
     *
     * @return array{message: string, created: int, queues: int, days: int, intervalHours: int}
     */
    public function generateRandomData(GenerateHistoricalCallDataRequest $request): array
    {
        // Get all queues
        $queues = $this->queueRepository->findAll();
        if (empty($queues)) {
            throw new \RuntimeException('No queues found. Please create queues first.');
        }

        $currentDate = new \DateTimeImmutable();
        $startDate = $currentDate->modify("-{$request->days} days");
        $totalCreated = 0;

        foreach ($queues as $queue) {
            $created = $this->generateDataForQueue($queue, $startDate, $currentDate, $request->intervalHours);
            $totalCreated += $created;
        }

        return [
            'message' => sprintf(
                'Successfully generated %d random historical call data entries for %d queue(s) over %d days',
                $totalCreated,
                count($queues),
                $request->days
            ),
            'created' => $totalCreated,
            'queues' => count($queues),
            'days' => $request->days,
            'intervalHours' => $request->intervalHours,
        ];
    }

    /**
     * Generate data for a single queue
     */
    private function generateDataForQueue(
        Queue $queue,
        \DateTimeImmutable $startDate,
        \DateTimeImmutable $endDate,
        int $intervalHours
    ): int {
        $dateTime = $startDate;
        $currentDay = null;
        $recordsInCurrentDay = 0;
        $skipCurrentDay = false;
        $created = 0;

        while ($dateTime <= $endDate) {
            $dayStart = $dateTime->format('Y-m-d');

            // Check if we've moved to a new day
            if ($currentDay !== $dayStart) {
                $currentDay = $dayStart;

                // Count existing records for this day
                $recordsInCurrentDay = $this->countRecordsForDay($queue, $dayStart);
                $skipCurrentDay = $recordsInCurrentDay >= 100;
            }

            // Skip generating if day already has 100 or more records
            if (!$skipCurrentDay) {
                // Check if entry already exists for this specific datetime
                $existingEntry = $this->historicalCallDataRepository->findByQueueAndDatetime($queue, $dateTime);

                if (!$existingEntry && $recordsInCurrentDay < 100) {
                    // Generate random data
                    $callCount = rand(10, 100);
                    $averageHandleTime = rand(120, 600) + (rand(0, 99) / 100);

                    $historicalData = new HistoricalCallData();
                    $historicalData->setQueue($queue);
                    $historicalData->setDatetime($dateTime);
                    $historicalData->setCallCount($callCount);
                    $historicalData->setAverageHandleTimeSeconds($averageHandleTime);

                    $this->historicalCallDataRepository->save($historicalData);
                    $created++;
                    $recordsInCurrentDay++;
                }
            }

            // Move to next interval
            $dateTime = $dateTime->modify("+{$intervalHours} hours");
        }

        // Flush changes for this queue
        $entityManager = $this->historicalCallDataRepository->getEntityManager();
        $entityManager->flush();

        return $created;
    }

    /**
     * Count existing records for a specific day and queue
     */
    private function countRecordsForDay(Queue $queue, string $dayStart): int
    {
        $dayStartDate = new \DateTimeImmutable($dayStart . ' 00:00:00');
        $dayEndDate = new \DateTimeImmutable($dayStart . ' 23:59:59');

        $count = $this->historicalCallDataRepository->createQueryBuilder('h')
            ->select('COUNT(h.id)')
            ->where('h.queue = :queue')
            ->andWhere('h.datetime >= :dayStart')
            ->andWhere('h.datetime <= :dayEnd')
            ->setParameter('queue', $queue)
            ->setParameter('dayStart', $dayStartDate)
            ->setParameter('dayEnd', $dayEndDate)
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $count;
    }
}
