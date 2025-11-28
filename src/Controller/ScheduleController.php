<?php

namespace App\Controller;

use App\DTO\CreateScheduleRequest;
use App\DTO\ScheduleResponse;
use App\Entity\AgentAvailability;
use App\Entity\AgentSkill;
use App\Entity\HistoricalCallData;
use App\Forecasting\ForecastRequestDTO;
use App\Forecasting\MovingAverageForecaster;
use App\Repository\AgentAvailabilityRepository;
use App\Repository\AgentSkillRepository;
use App\Repository\HistoricalCallDataRepository;
use App\Scheduling\AgentAvailabilityDTO;
use App\Scheduling\AgentSkillDTO;
use App\Scheduling\InitialScheduleGenerator;
use App\Scheduling\ScheduleGenerationInputDTO;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/schedules')]
class ScheduleController extends AbstractController
{
    public function __construct(
        private readonly HistoricalCallDataRepository $historicalCallDataRepository,
        private readonly AgentAvailabilityRepository $agentAvailabilityRepository,
        private readonly AgentSkillRepository $agentSkillRepository,
        private readonly MovingAverageForecaster $forecaster,
        private readonly InitialScheduleGenerator $scheduleGenerator
    ) {
    }

    /**
     * Create a new schedule based on forecasted demand
     */
    #[Route('', methods: ['POST'])]
    public function create(
        #[MapRequestPayload] CreateScheduleRequest $request
    ): JsonResponse {
        try {
            // Parse dates
            $scheduleStartDate = new \DateTimeImmutable($request->scheduleStartDate);
            $scheduleEndDate = new \DateTimeImmutable($request->scheduleEndDate);

            // Validate date range
            if ($scheduleEndDate <= $scheduleStartDate) {
                return $this->json(
                    ['error' => 'Schedule end date must be after start date'],
                    Response::HTTP_BAD_REQUEST
                );
            }

            // Step 1: Fetch historical call data for forecasting
            $historicalData = $this->fetchHistoricalData(
                $request->queueNames,
                $scheduleStartDate,
                $request->lookbackWeeks
            );

            if (empty($historicalData)) {
                return $this->json(
                    ['error' => 'No historical call data available for the specified queues'],
                    Response::HTTP_BAD_REQUEST
                );
            }

            // Step 2: Generate demand forecasts for each queue and time slot
            $demandForecasts = $this->generateDemandForecasts(
                $request->queueNames,
                $scheduleStartDate,
                $scheduleEndDate,
                $historicalData,
                $request->timeSlotGranularityMinutes,
                $request->lookbackWeeks,
                $request->shrinkageFactor,
                $request->targetOccupancy
            );

            // Step 3: Fetch agent availabilities for the schedule period
            $agentAvailabilities = $this->fetchAgentAvailabilities(
                $scheduleStartDate,
                $scheduleEndDate
            );

            if (empty($agentAvailabilities)) {
                return $this->json(
                    ['error' => 'No agent availabilities found for the schedule period'],
                    Response::HTTP_BAD_REQUEST
                );
            }

            // Step 4: Fetch agent skills
            $agentSkills = $this->fetchAgentSkills($request->queueNames);

            if (empty($agentSkills)) {
                return $this->json(
                    ['error' => 'No agent skills found for the specified queues'],
                    Response::HTTP_BAD_REQUEST
                );
            }

            // Step 5: Generate the optimized schedule
            $scheduleInput = new ScheduleGenerationInputDTO(
                agentAvailabilities: $agentAvailabilities,
                agentSkills: $agentSkills,
                demandForecasts: $demandForecasts,
                scheduleStartDate: $scheduleStartDate,
                scheduleEndDate: $scheduleEndDate,
                constraints: $request->constraints,
                timeSlotGranularityMinutes: $request->timeSlotGranularityMinutes
            );

            $scheduleOutput = $this->scheduleGenerator->generate($scheduleInput);

            // Step 6: Return the schedule
            $response = ScheduleResponse::fromScheduleOutput($scheduleOutput);

            return $this->json($response, Response::HTTP_CREATED);
        } catch (\Exception $e) {
            return $this->json(
                ['error' => 'Failed to generate schedule: ' . $e->getMessage()],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * Fetch historical call data for forecasting
     *
     * @param string[] $queueNames
     * @return array<int, \App\Forecasting\HistoricalCallDataDTO>
     */
    private function fetchHistoricalData(
        array $queueNames,
        \DateTimeImmutable $scheduleStartDate,
        int $lookbackWeeks
    ): array {
        $cutoffDate = $scheduleStartDate->modify('-' . $lookbackWeeks . ' weeks');

        $historicalEntities = $this->historicalCallDataRepository->createQueryBuilder('h')
            ->join('h.queue', 'q')
            ->where('q.name IN (:queueNames)')
            ->andWhere('h.datetime >= :cutoffDate')
            ->andWhere('h.datetime < :scheduleStartDate')
            ->setParameter('queueNames', $queueNames)
            ->setParameter('cutoffDate', $cutoffDate)
            ->setParameter('scheduleStartDate', $scheduleStartDate)
            ->orderBy('h.datetime', 'DESC')
            ->getQuery()
            ->getResult();

        return array_map(
            fn(HistoricalCallData $entity) => $entity->toDTO(),
            $historicalEntities
        );
    }

    /**
     * Generate demand forecasts for all queues and time slots
     *
     * @param string[] $queueNames
     * @param array<int, \App\Forecasting\HistoricalCallDataDTO> $historicalData
     * @return array<int, \App\Scheduling\DemandForecastDTO>
     */
    private function generateDemandForecasts(
        array $queueNames,
        \DateTimeImmutable $scheduleStartDate,
        \DateTimeImmutable $scheduleEndDate,
        array $historicalData,
        int $timeGranularityMinutes,
        int $lookbackWeeks,
        float $shrinkageFactor,
        float $targetOccupancy
    ): array {
        $demandForecasts = [];

        // Generate forecasts for each queue
        foreach ($queueNames as $queueName) {
            // Generate time slots for the schedule period
            $current = $scheduleStartDate;
            $interval = new \DateInterval('PT' . $timeGranularityMinutes . 'M');

            while ($current < $scheduleEndDate) {
                // Create forecast request for this time slot
                $forecastRequest = new ForecastRequestDTO(
                    queueName: $queueName,
                    targetDatetime: $current,
                    historicalData: $historicalData,
                    timeGranularityMinutes: $timeGranularityMinutes,
                    lookbackWeeks: $lookbackWeeks,
                    shrinkageFactor: $shrinkageFactor,
                    targetOccupancy: $targetOccupancy
                );

                // Generate forecast
                $forecastResult = $this->forecaster->forecast($forecastRequest);

                // Convert to DemandForecastDTO
                $demandForecasts[] = $forecastResult->toDemandForecastDTO();

                $current = $current->add($interval);
            }
        }

        return $demandForecasts;
    }

    /**
     * Fetch agent availabilities for the schedule period
     *
     * @return array<int, AgentAvailabilityDTO>
     */
    private function fetchAgentAvailabilities(
        \DateTimeImmutable $scheduleStartDate,
        \DateTimeImmutable $scheduleEndDate
    ): array {
        $availabilityEntities = $this->agentAvailabilityRepository->createQueryBuilder('aa')
            ->where('aa.startTime < :scheduleEndDate')
            ->andWhere('aa.endTime > :scheduleStartDate')
            ->setParameter('scheduleStartDate', $scheduleStartDate)
            ->setParameter('scheduleEndDate', $scheduleEndDate)
            ->getQuery()
            ->getResult();

        return array_map(
            fn(AgentAvailability $entity) => new AgentAvailabilityDTO(
                agentId: $entity->getAgent()->getId(),
                startTime: $entity->getStartTime(),
                endTime: $entity->getEndTime(),
                isAvailable: $entity->isAvailable()
            ),
            $availabilityEntities
        );
    }

    /**
     * Fetch agent skills for the specified queues
     *
     * @param string[] $queueNames
     * @return array<int, AgentSkillDTO>
     */
    private function fetchAgentSkills(array $queueNames): array
    {
        $skillEntities = $this->agentSkillRepository->createQueryBuilder('ags')
            ->join('ags.queue', 'q')
            ->where('q.name IN (:queueNames)')
            ->setParameter('queueNames', $queueNames)
            ->getQuery()
            ->getResult();

        return array_map(
            fn(AgentSkill $entity) => new AgentSkillDTO(
                agentId: $entity->getAgent()->getId(),
                queueName: $entity->getQueue()->getName(),
                efficiencyCoefficient: $entity->getEfficiencyCoefficient(),
                skillLevel: $entity->getSkillLevel(),
                isPrimary: $entity->isPrimary()
            ),
            $skillEntities
        );
    }
}
