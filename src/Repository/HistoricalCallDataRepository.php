<?php

namespace App\Repository;

use App\Entity\HistoricalCallData;
use App\Entity\Queue;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<HistoricalCallData>
 */
class HistoricalCallDataRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, HistoricalCallData::class);
    }

    /**
     * Save historical call data
     */
    public function save(HistoricalCallData $data, bool $flush = false): void
    {
        $this->getEntityManager()->persist($data);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Remove historical call data
     */
    public function remove(HistoricalCallData $data, bool $flush = false): void
    {
        $this->getEntityManager()->remove($data);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Find data for a specific queue and date range
     *
     * @return HistoricalCallData[]
     */
    public function findByQueueAndDateRange(
        Queue $queue,
        \DateTimeImmutable $startDate,
        \DateTimeImmutable $endDate
    ): array {
        return $this->createQueryBuilder('h')
            ->where('h.queue = :queue')
            ->andWhere('h.datetime >= :startDate')
            ->andWhere('h.datetime < :endDate')
            ->setParameter('queue', $queue)
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->orderBy('h.datetime', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find data for a specific queue name and date range
     *
     * @return HistoricalCallData[]
     */
    public function findByQueueNameAndDateRange(
        string $queueName,
        \DateTimeImmutable $startDate,
        \DateTimeImmutable $endDate
    ): array {
        return $this->createQueryBuilder('h')
            ->join('h.queue', 'q')
            ->where('q.name = :queueName')
            ->andWhere('h.datetime >= :startDate')
            ->andWhere('h.datetime < :endDate')
            ->setParameter('queueName', $queueName)
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->orderBy('h.datetime', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find data for specific day of week and time
     * Useful for forecasting - finds historical data for same day/time
     *
     * @return HistoricalCallData[]
     */
    public function findByDayOfWeekAndTime(
        Queue $queue,
        int $dayOfWeek,
        int $hour,
        int $minute = 0,
        int $lookbackWeeks = 4
    ): array {
        $endDate = new \DateTimeImmutable();
        $startDate = $endDate->modify("-{$lookbackWeeks} weeks");

        // Use database functions to filter by day/time
        return $this->createQueryBuilder('h')
            ->where('h.queue = :queue')
            ->andWhere('DAYOFWEEK(h.datetime) = :dayOfWeek') // MySQL: 1=Sunday, 2=Monday, etc.
            ->andWhere('HOUR(h.datetime) = :hour')
            ->andWhere('MINUTE(h.datetime) = :minute')
            ->andWhere('h.datetime >= :startDate')
            ->andWhere('h.datetime < :endDate')
            ->setParameter('queue', $queue)
            ->setParameter('dayOfWeek', $dayOfWeek === 7 ? 1 : $dayOfWeek + 1) // Convert ISO to MySQL
            ->setParameter('hour', $hour)
            ->setParameter('minute', $minute)
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->orderBy('h.datetime', 'DESC')
            ->setMaxResults($lookbackWeeks)
            ->getQuery()
            ->getResult();
    }

    /**
     * Get all historical data for forecasting purposes
     *
     * @return HistoricalCallData[]
     */
    public function findForForecasting(
        string $queueName,
        int $lookbackWeeks = 12
    ): array {
        $endDate = new \DateTimeImmutable();
        $startDate = $endDate->modify("-{$lookbackWeeks} weeks");

        return $this->createQueryBuilder('h')
            ->join('h.queue', 'q')
            ->where('q.name = :queueName')
            ->andWhere('h.datetime >= :startDate')
            ->andWhere('h.datetime < :endDate')
            ->setParameter('queueName', $queueName)
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->orderBy('h.datetime', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find data by specific datetime
     */
    public function findByQueueAndDatetime(
        Queue $queue,
        \DateTimeImmutable $datetime
    ): ?HistoricalCallData {
        return $this->createQueryBuilder('h')
            ->where('h.queue = :queue')
            ->andWhere('h.datetime = :datetime')
            ->setParameter('queue', $queue)
            ->setParameter('datetime', $datetime)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Get average call volume for a queue by hour of day
     *
     * @return array<int, float> Array indexed by hour (0-23)
     */
    public function getAverageVolumeByHour(
        Queue $queue,
        int $lookbackDays = 30
    ): array {
        $startDate = (new \DateTimeImmutable())->modify("-{$lookbackDays} days");

        $results = $this->createQueryBuilder('h')
            ->select('HOUR(h.datetime) as hour, AVG(h.callCount) as avgCalls')
            ->where('h.queue = :queue')
            ->andWhere('h.datetime >= :startDate')
            ->setParameter('queue', $queue)
            ->setParameter('startDate', $startDate)
            ->groupBy('hour')
            ->orderBy('hour', 'ASC')
            ->getQuery()
            ->getResult();

        $averages = array_fill(0, 24, 0.0);
        foreach ($results as $result) {
            $averages[(int)$result['hour']] = (float)$result['avgCalls'];
        }

        return $averages;
    }

    /**
     * Get average call volume for a queue by day of week
     *
     * @return array<int, float> Array indexed by day (1-7)
     */
    public function getAverageVolumeByDayOfWeek(
        Queue $queue,
        int $lookbackWeeks = 4
    ): array {
        $startDate = (new \DateTimeImmutable())->modify("-{$lookbackWeeks} weeks");

        $results = $this->createQueryBuilder('h')
            ->select('DAYOFWEEK(h.datetime) as dayOfWeek, AVG(h.callCount) as avgCalls')
            ->where('h.queue = :queue')
            ->andWhere('h.datetime >= :startDate')
            ->setParameter('queue', $queue)
            ->setParameter('startDate', $startDate)
            ->groupBy('dayOfWeek')
            ->orderBy('dayOfWeek', 'ASC')
            ->getQuery()
            ->getResult();

        $averages = array_fill(1, 7, 0.0);
        foreach ($results as $result) {
            $mysqlDay = (int)$result['dayOfWeek'];
            $isoDay = $mysqlDay === 1 ? 7 : $mysqlDay - 1; // Convert MySQL to ISO
            $averages[$isoDay] = (float)$result['avgCalls'];
        }

        return $averages;
    }

    /**
     * Get total calls for a date range
     */
    public function getTotalCallsForPeriod(
        Queue $queue,
        \DateTimeImmutable $startDate,
        \DateTimeImmutable $endDate
    ): int {
        $result = $this->createQueryBuilder('h')
            ->select('SUM(h.callCount)')
            ->where('h.queue = :queue')
            ->andWhere('h.datetime >= :startDate')
            ->andWhere('h.datetime < :endDate')
            ->setParameter('queue', $queue)
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->getQuery()
            ->getSingleScalarResult();

        return (int) ($result ?? 0);
    }

    /**
     * Get average service level achieved for a period
     */
    public function getAverageServiceLevel(
        Queue $queue,
        \DateTimeImmutable $startDate,
        \DateTimeImmutable $endDate
    ): ?float {
        $result = $this->createQueryBuilder('h')
            ->select('AVG(h.serviceLevelAchieved)')
            ->where('h.queue = :queue')
            ->andWhere('h.datetime >= :startDate')
            ->andWhere('h.datetime < :endDate')
            ->andWhere('h.serviceLevelAchieved IS NOT NULL')
            ->setParameter('queue', $queue)
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->getQuery()
            ->getSingleScalarResult();

        return $result !== null ? (float) $result : null;
    }

    /**
     * Delete old historical data
     * Useful for data retention policies
     */
    public function deleteOlderThan(\DateTimeImmutable $date): int
    {
        return $this->createQueryBuilder('h')
            ->delete()
            ->where('h.datetime < :date')
            ->setParameter('date', $date)
            ->getQuery()
            ->execute();
    }

    /**
     * Convert historical data to DTOs for forecasting
     *
     * @param HistoricalCallData[] $historicalData
     * @return \App\Forecasting\HistoricalCallDataDTO[]
     */
    public function convertToDTOs(array $historicalData): array
    {
        return array_map(
            fn(HistoricalCallData $data) => $data->toDTO(),
            $historicalData
        );
    }
}
