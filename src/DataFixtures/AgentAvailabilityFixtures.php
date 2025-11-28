<?php

namespace App\DataFixtures;

use App\Entity\Agent;
use App\Entity\AgentAvailability;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * Fixture for loading agent availability data
 * Creates realistic work schedules for agents
 */
class AgentAvailabilityFixtures extends Fixture implements DependentFixtureInterface
{
    private const WEEKS_TO_GENERATE = 4;

    /**
     * Shift configurations
     */
    private const SHIFTS = [
        'morning' => ['start' => '08:00:00', 'end' => '16:00:00'],
        'afternoon' => ['start' => '12:00:00', 'end' => '20:00:00'],
        'full_day' => ['start' => '09:00:00', 'end' => '17:00:00'],
    ];

    /**
     * Load agent availability fixtures
     */
    public function load(ObjectManager $manager): void
    {
        $agents = $manager->getRepository(Agent::class)->findAll();


        foreach ($agents as $agentIndex => $agent) {
            // Assign shift pattern to agent (rotating)
            $shiftType = $this->getShiftTypeForAgent($agentIndex);

            // Generate availability for next 4 weeks
            $this->generateAvailabilityForAgent($manager, $agent, $shiftType);
        }


        $manager->flush();
    }

    /**
     * Generate availability schedule for an agent
     */
    private function generateAvailabilityForAgent(
        ObjectManager $manager,
        Agent $agent,
        string $shiftType
    ): void {
        $today = new \DateTime('today');

        for ($week = 0; $week < self::WEEKS_TO_GENERATE; $week++) {
            // Generate Monday-Friday schedules
            for ($day = 0; $day < 5; $day++) {
                $date = (clone $today)
                    ->modify("+{$week} weeks")
                    ->modify("this week")
                    ->modify("+{$day} days");

                // Randomly mark some days as unavailable (10% chance)
                $isAvailable = mt_rand(1, 100) > 10;

                // Skip weekends
                if ($date->format('N') >= 6) {
                    continue;
                }

                $shift = self::SHIFTS[$shiftType];

                $startTime = (clone $date)->modify($shift['start']);
                $endTime = (clone $date)->modify($shift['end']);

                $availability = new AgentAvailability();
                $availability->setAgent($agent);
                $availability->setStartTime($startTime);
                $availability->setEndTime($endTime);
                $availability->setIsAvailable($isAvailable);

                $manager->persist($availability);
            }

            // Add some weekend availability for some agents (30% chance)
            if (mt_rand(1, 100) <= 30) {
                $this->addWeekendAvailability($manager, $agent, $today, $week);
            }
        }
    }

    /**
     * Add weekend availability for agents
     */
    private function addWeekendAvailability(
        ObjectManager $manager,
        Agent $agent,
        \DateTime $today,
        int $week
    ): void {
        // Saturday
        $saturday = (clone $today)
            ->modify("+{$week} weeks")
            ->modify("this week")
            ->modify("+5 days")
            ->modify('09:00:00');

        $saturdayEnd = (clone $saturday)->modify('14:00:00'); // Half day

        $saturdayAvailability = new AgentAvailability();
        $saturdayAvailability->setAgent($agent);
        $saturdayAvailability->setStartTime($saturday);
        $saturdayAvailability->setEndTime($saturdayEnd);
        $saturdayAvailability->setIsAvailable(true);

        $manager->persist($saturdayAvailability);
    }

    /**
     * Determine shift type for agent (rotating pattern)
     */
    private function getShiftTypeForAgent(int $agentIndex): string
    {
        $shiftTypes = array_keys(self::SHIFTS);
        return $shiftTypes[$agentIndex % count($shiftTypes)];
    }

    /**
     * Specify fixture dependencies
     */
    public function getDependencies(): array
    {
        return [
            AgentFixtures::class,
        ];
    }
}
