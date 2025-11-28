<?php

namespace App\DataFixtures;

use App\Entity\Agent;
use App\Entity\AgentSkill;
use App\Entity\Queue;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * Fixture for loading agent skill data
 * Assigns queues and skill levels to agents
 */
class AgentSkillFixtures extends Fixture implements DependentFixtureInterface
{
    /**
     * Skill level ranges and efficiency coefficients
     */
    private const SKILL_LEVELS = [
        'expert' => ['level' => 5, 'efficiency' => [0.95, 1.0]],
        'advanced' => ['level' => 4, 'efficiency' => [0.85, 0.95]],
        'intermediate' => ['level' => 3, 'efficiency' => [0.75, 0.85]],
        'beginner' => ['level' => 2, 'efficiency' => [0.65, 0.75]],
        'trainee' => ['level' => 1, 'efficiency' => [0.5, 0.65]],
    ];

    /**
     * Load agent skill fixtures
     */
    public function load(ObjectManager $manager): void
    {
        $agents = $manager->getRepository(Agent::class)->findAll();
        $queues = $manager->getRepository(Queue::class)->findAll();

        if (empty($queues)) {
            return; // Skip if no queues exist
        }

        foreach ($agents as $agentIndex => $agent) {
            // Each agent gets 2-4 queue skills
            $numberOfSkills = mt_rand(2, 4);
            $assignedQueues = $this->selectRandomQueues($queues, $numberOfSkills);

            foreach ($assignedQueues as $index => $queue) {
                // First skill is primary, others are secondary
                $isPrimary = ($index === 0);

                // Primary skills tend to be higher level
                $skillCategory = $this->getSkillCategory($isPrimary);
                $skillConfig = self::SKILL_LEVELS[$skillCategory];

                $agentSkill = new AgentSkill(
                    efficiencyCoefficient: $this->getRandomEfficiency($skillConfig['efficiency']),
                    skillLevel: $skillConfig['level'],
                    isPrimary: $isPrimary,
                    agent: $agent,
                    queue: $queue
                );

                $manager->persist($agentSkill);
            }
        }

        $manager->flush();
    }

    /**
     * Select random queues for an agent
     */
    private function selectRandomQueues(array $queues, int $count): array
    {
        $shuffled = $queues;
        shuffle($shuffled);
        return array_slice($shuffled, 0, min($count, count($queues)));
    }

    /**
     * Get skill category based on whether it's primary or secondary
     */
    private function getSkillCategory(bool $isPrimary): string
    {
        if ($isPrimary) {
            // Primary skills are usually higher (70% expert/advanced, 30% intermediate)
            $rand = mt_rand(1, 100);
            return match(true) {
                $rand <= 40 => 'expert',
                $rand <= 70 => 'advanced',
                default => 'intermediate'
            };
        } else {
            // Secondary skills are more varied
            $rand = mt_rand(1, 100);
            return match(true) {
                $rand <= 20 => 'advanced',
                $rand <= 50 => 'intermediate',
                $rand <= 80 => 'beginner',
                default => 'trainee'
            };
        }
    }

    /**
     * Get random efficiency coefficient within range
     */
    private function getRandomEfficiency(array $range): float
    {
        $min = $range[0];
        $max = $range[1];

        // Generate random float between min and max
        $random = $min + (mt_rand() / mt_getrandmax()) * ($max - $min);

        return round($random, 2);
    }

    /**
     * Specify fixture dependencies
     */
    public function getDependencies(): array
    {
        return [
            AgentFixtures::class,
            QueueFixtures::class,
        ];
    }
}
