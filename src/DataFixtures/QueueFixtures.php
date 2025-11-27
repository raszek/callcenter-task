<?php

namespace App\DataFixtures;

use App\Entity\Queue;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

/**
 * Fixture for loading sample Queue data into the database
 */
class QueueFixtures extends Fixture
{
    /**
     * Load queue fixtures
     */
    public function load(ObjectManager $manager): void
    {
        $queuesData = $this->getQueuesData();

        foreach ($queuesData as $data) {
            $queue = new Queue();
            $queue
                ->setName($data['name'])
                ->setDisplayName($data['displayName'])
                ->setTargetServiceLevel($data['targetServiceLevel'])
                ->setTargetAnswerTimeSeconds($data['targetAnswerTimeSeconds']);

            $manager->persist($queue);

            // Store reference for use in other fixtures
            $this->addReference('queue_' . $data['name'], $queue);
        }

        $manager->flush();
    }

    /**
     * Get queue configuration data
     *
     * @return array<int, array<string, mixed>>
     */
    private function getQueuesData(): array
    {
        return [
            [
                'name' => 'sales',
                'displayName' => 'Sales Team',
                'targetServiceLevel' => 0.80,
                'targetAnswerTimeSeconds' => 20,
            ],
            [
                'name' => 'technical_support',
                'displayName' => 'Technical Support',
                'targetServiceLevel' => 0.85,
                'targetAnswerTimeSeconds' => 30,
            ],
            [
                'name' => 'customer_complaints',
                'displayName' => 'Customer Complaints',
                'targetServiceLevel' => 0.90,
                'targetAnswerTimeSeconds' => 15,
            ],
            [
                'name' => 'billing',
                'displayName' => 'Billing Inquiries',
                'targetServiceLevel' => 0.75,
                'targetAnswerTimeSeconds' => 40,
            ],
            [
                'name' => 'general_inquiries',
                'displayName' => 'General Inquiries',
                'targetServiceLevel' => 0.70,
                'targetAnswerTimeSeconds' => 60,
            ],
            [
                'name' => 'vip_support',
                'displayName' => 'VIP Customer Support',
                'targetServiceLevel' => 0.95,
                'targetAnswerTimeSeconds' => 10,
            ],
            [
                'name' => 'order_status',
                'displayName' => 'Order Status',
                'targetServiceLevel' => 0.75,
                'targetAnswerTimeSeconds' => 45,
            ],
            [
                'name' => 'returns_exchanges',
                'displayName' => 'Returns & Exchanges',
                'targetServiceLevel' => 0.80,
                'targetAnswerTimeSeconds' => 30,
            ],
        ];
    }
}
