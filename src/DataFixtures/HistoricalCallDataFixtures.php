<?php

namespace App\DataFixtures;

use App\Entity\HistoricalCallData;
use App\Entity\Queue;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * Fixture for loading historical call data
 * Generates realistic call patterns for forecasting and analytics
 */
class HistoricalCallDataFixtures extends Fixture implements DependentFixtureInterface
{
    private const WEEKS_OF_DATA = 8;
    private const WORKING_HOURS_START = 8;
    private const WORKING_HOURS_END = 18;
    private const TIME_SLOT_MINUTES = 30;

    /**
     * Load historical call data fixtures
     */
    public function load(ObjectManager $manager): void
    {
        $queues = $this->getQueueConfigurations();
        $endDate = new \DateTimeImmutable('today');

        foreach ($queues as $queueName => $config) {
            /** @var Queue $queue */
            $queueEntity = $manager->getRepository(Queue::class)->findOneBy(['name' => $queueName]);

            if (!$queueEntity) {
                continue; // Skip if queue not found
            }

            $queueId = $queueEntity->getId();

            // Generate data for past weeks
            for ($week = 1; $week <= self::WEEKS_OF_DATA; $week++) {
                // Generate data for weekdays (Monday-Friday)
                for ($day = 0; $day < 5; $day++) {
                    $date = $endDate
                        ->modify("-{$week} weeks")
                        ->modify("this week")
                        ->modify("+{$day} days");

                    // Re-fetch queue entity after clear() to avoid detached entity issues
                    $queue = $manager->getReference(Queue::class, $queueId);

                    $this->generateDayData($manager, $queue, $date, $config);

                    // Flush and clear to free memory
                    $manager->flush();
                    $manager->clear();
                }
            }

        }
    }

    /**
     * Generate call data for a single day
     */
    private function generateDayData(
        ObjectManager $manager,
        Queue $queue,
        \DateTimeImmutable $date,
        array $config
    ): void {
        $currentTime = $date->setTime(self::WORKING_HOURS_START, 0);
        $endTime = $date->setTime(self::WORKING_HOURS_END, 0);

        while ($currentTime < $endTime) {
            $data = new HistoricalCallData();

            $hour = (int) $currentTime->format('H');
            $dayOfWeek = (int) $currentTime->format('N');

            // Calculate call volume based on patterns
            $callCount = $this->calculateCallCount($config, $hour, $dayOfWeek);
            $handleTime = $this->calculateHandleTime($config['baseHandleTime']);

            $data
                ->setQueue($queue)
                ->setDatetime($currentTime)
                ->setCallCount($callCount)
                ->setAverageHandleTimeSeconds($handleTime);

            $manager->persist($data);

            // Move to next time slot
            $currentTime = $currentTime->modify('+' . self::TIME_SLOT_MINUTES . ' minutes');
        }
    }

    /**
     * Calculate call count based on hour and day patterns
     */
    private function calculateCallCount(array $config, int $hour, int $dayOfWeek): int
    {
        $baseVolume = $config['baseVolume'];

        // Hour of day factor (peak hours)
        $hourFactor = $this->getHourFactor($hour);

        // Day of week factor
        $dayFactor = $this->getDayOfWeekFactor($dayOfWeek);

        // Random variation (±15%)
        $randomFactor = 1.0 + (mt_rand(-15, 15) / 100);

        $callCount = $baseVolume * $hourFactor * $dayFactor * $randomFactor;

        return max(0, (int) round($callCount));
    }

    /**
     * Get hour factor for call volume (peak hours pattern)
     */
    private function getHourFactor(int $hour): float
    {
        // Typical call center pattern
        return match(true) {
            $hour >= 8 && $hour < 9 => 0.8,   // Early morning - slow start
            $hour >= 9 && $hour < 12 => 1.3,  // Morning peak
            $hour >= 12 && $hour < 14 => 0.7, // Lunch dip
            $hour >= 14 && $hour < 16 => 1.2, // Afternoon peak
            $hour >= 16 && $hour < 18 => 0.9, // End of day decline
            default => 0.5
        };
    }

    /**
     * Get day of week factor for call volume
     */
    private function getDayOfWeekFactor(int $dayOfWeek): float
    {
        // Monday = 1, Friday = 5
        return match($dayOfWeek) {
            1 => 1.3,  // Monday - highest (weekend backlog)
            2 => 1.1,  // Tuesday - high
            3 => 1.0,  // Wednesday - normal
            4 => 0.95, // Thursday - slightly lower
            5 => 0.85, // Friday - lower (people leaving early)
            default => 0.5 // Weekend (if included)
        };
    }

    /**
     * Calculate handle time with variation
     */
    private function calculateHandleTime(float $baseHandleTime): float
    {
        // Random variation (±10%)
        $variation = 1.0 + (mt_rand(-10, 10) / 100);
        return round($baseHandleTime * $variation, 1);
    }

    /**
     * Get queue configurations with base volumes and handle times
     */
    private function getQueueConfigurations(): array
    {
        return [
            'sales' => [
                'baseVolume' => 40,      // Base calls per 30-min slot
                'baseHandleTime' => 320, // Average handle time in seconds
            ],
            'technical_support' => [
                'baseVolume' => 35,
                'baseHandleTime' => 480, // Longer calls for technical issues
            ],
            'customer_complaints' => [
                'baseVolume' => 15,
                'baseHandleTime' => 420, // Complex calls
            ],
            'billing' => [
                'baseVolume' => 25,
                'baseHandleTime' => 280,
            ],
            'general_inquiries' => [
                'baseVolume' => 30,
                'baseHandleTime' => 240, // Shorter, simpler calls
            ],
            'vip_support' => [
                'baseVolume' => 8,
                'baseHandleTime' => 360, // Fewer but important calls
            ],
            'order_status' => [
                'baseVolume' => 20,
                'baseHandleTime' => 180, // Quick status checks
            ],
            'returns_exchanges' => [
                'baseVolume' => 18,
                'baseHandleTime' => 340,
            ],
        ];
    }

    /**
     * Specify fixture dependencies
     */
    public function getDependencies(): array
    {
        return [
            QueueFixtures::class,
        ];
    }
}
