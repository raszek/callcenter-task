<?php

namespace App\DataFixtures;

use App\Entity\Agent;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

/**
 * Fixture for loading agent data
 * Creates sample agents for testing and development
 */
class AgentFixtures extends Fixture
{
    public const AGENT_REFERENCE_PREFIX = 'agent_';

    /**
     * Load agent fixtures
     */
    public function load(ObjectManager $manager): void
    {
        $agents = $this->getAgentData();

        foreach ($agents as $index => $agentData) {
            $agent = new Agent(
                firstName: $agentData['firstName'],
                lastName: $agentData['lastName']
            );

            $manager->persist($agent);

            // Store reference for use in dependent fixtures
            $this->addReference(self::AGENT_REFERENCE_PREFIX . $index, $agent);
        }

        $manager->flush();
    }

    /**
     * Get sample agent data
     */
    private function getAgentData(): array
    {
        return [
            ['firstName' => 'John', 'lastName' => 'Smith'],
            ['firstName' => 'Sarah', 'lastName' => 'Johnson'],
            ['firstName' => 'Michael', 'lastName' => 'Williams'],
            ['firstName' => 'Emily', 'lastName' => 'Brown'],
            ['firstName' => 'David', 'lastName' => 'Jones'],
            ['firstName' => 'Jessica', 'lastName' => 'Garcia'],
            ['firstName' => 'James', 'lastName' => 'Miller'],
            ['firstName' => 'Jennifer', 'lastName' => 'Davis'],
            ['firstName' => 'Robert', 'lastName' => 'Rodriguez'],
            ['firstName' => 'Linda', 'lastName' => 'Martinez'],
            ['firstName' => 'William', 'lastName' => 'Hernandez'],
            ['firstName' => 'Patricia', 'lastName' => 'Lopez'],
            ['firstName' => 'Richard', 'lastName' => 'Gonzalez'],
            ['firstName' => 'Mary', 'lastName' => 'Wilson'],
            ['firstName' => 'Thomas', 'lastName' => 'Anderson'],
            ['firstName' => 'Barbara', 'lastName' => 'Thomas'],
            ['firstName' => 'Christopher', 'lastName' => 'Taylor'],
            ['firstName' => 'Elizabeth', 'lastName' => 'Moore'],
            ['firstName' => 'Daniel', 'lastName' => 'Jackson'],
            ['firstName' => 'Susan', 'lastName' => 'Martin'],
        ];
    }
}
