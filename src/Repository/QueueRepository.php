<?php

namespace App\Repository;

use App\Entity\Queue;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Queue>
 */
class QueueRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Queue::class);
    }

    /**
     * Save a queue entity
     */
    public function save(Queue $queue, bool $flush = false): void
    {
        $this->getEntityManager()->persist($queue);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Remove a queue entity
     */
    public function remove(Queue $queue, bool $flush = false): void
    {
        $this->getEntityManager()->remove($queue);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Find all active queues
     *
     * @return Queue[]
     */
    public function findActive(): array
    {
        return $this->createQueryBuilder('q')
            ->where('q.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('q.priority', 'DESC')
            ->addOrderBy('q.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find queue by name
     */
    public function findOneByName(string $name): ?Queue
    {
        return $this->createQueryBuilder('q')
            ->where('q.name = :name')
            ->setParameter('name', $name)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find all queues ordered by priority
     *
     * @return Queue[]
     */
    public function findAllOrdered(): array
    {
        return $this->createQueryBuilder('q')
            ->orderBy('q.priority', 'DESC')
            ->addOrderBy('q.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find queues by priority level
     *
     * @return Queue[]
     */
    public function findByPriority(int $priority): array
    {
        return $this->createQueryBuilder('q')
            ->where('q.priority = :priority')
            ->andWhere('q.isActive = :active')
            ->setParameter('priority', $priority)
            ->setParameter('active', true)
            ->orderBy('q.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find high priority queues (priority >= threshold)
     *
     * @return Queue[]
     */
    public function findHighPriority(int $threshold = 5): array
    {
        return $this->createQueryBuilder('q')
            ->where('q.priority >= :threshold')
            ->andWhere('q.isActive = :active')
            ->setParameter('threshold', $threshold)
            ->setParameter('active', true)
            ->orderBy('q.priority', 'DESC')
            ->addOrderBy('q.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Count total number of active queues
     */
    public function countActive(): int
    {
        return (int) $this->createQueryBuilder('q')
            ->select('COUNT(q.id)')
            ->where('q.isActive = :active')
            ->setParameter('active', true)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Get queue names as array (useful for dropdowns)
     *
     * @return array<int, string> Array of [id => name]
     */
    public function getQueueNamesArray(): array
    {
        $queues = $this->createQueryBuilder('q')
            ->where('q.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('q.displayName', 'ASC')
            ->getQuery()
            ->getResult();

        $result = [];
        foreach ($queues as $queue) {
            $result[$queue->getId()] = $queue->getDisplayName();
        }

        return $result;
    }
}
