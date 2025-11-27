<?php

namespace App\Forecasting;

/**
 * Implements Moving Average forecasting algorithm for call center demand prediction
 *
 * Algorithm:
 * 1. Filter historical data for matching day-of-week and time-of-day
 * 2. Calculate average of last N weeks (moving average)
 * 3. Calculate standard deviation for confidence intervals
 * 4. Convert call volume to required FTE using workload calculation
 * 5. Apply shrinkage and occupancy factors
 *
 * No services - pure calculation class using DTOs
 */
class MovingAverageForecaster
{
    /**
     * Calculate forecast for a single time slot
     */
    public function forecast(ForecastRequestDTO $request): ForecastResultDTO
    {
        // Step 1: Filter historical data to matching time slots
        $matchingData = $this->filterMatchingTimeSlots($request);

        if (empty($matchingData)) {
            return $this->createEmptyForecast($request);
        }

        // Step 2: Calculate moving average of call volumes
        $forecastedCalls = $this->calculateMovingAverage($matchingData);

        // Step 3: Calculate average handle time
        $averageHandleTime = $this->calculateAverageHandleTime($matchingData);

        // Step 4: Calculate standard deviation for confidence intervals
        $standardDeviation = $this->calculateStandardDeviation($matchingData);

        // Step 5: Calculate required FTE
        $requiredFTE = $this->calculateRequiredFTE(
            $forecastedCalls,
            $averageHandleTime,
            $request
        );

        // Step 6: Calculate confidence intervals
        [$confidenceLower, $confidenceUpper] = $this->calculateConfidenceIntervals(
            $forecastedCalls,
            $standardDeviation,
            $averageHandleTime,
            $request
        );

        // Step 7: Build result DTO
        return new ForecastResultDTO(
            queueName: $request->getQueueName(),
            startTime: $request->getTargetDatetime(),
            endTime: $request->getTargetEndDatetime(),
            forecastedCalls: $forecastedCalls,
            averageHandleTimeSeconds: $averageHandleTime,
            requiredFTE: $requiredFTE,
            confidenceLowerFTE: $confidenceLower,
            confidenceUpperFTE: $confidenceUpper,
            dataPointsUsed: count($matchingData),
            standardDeviation: $standardDeviation,
            metadata: [
                'algorithm' => 'moving_average',
                'lookback_weeks' => $request->getLookbackWeeks(),
                'historical_calls' => array_map(fn($d) => $d->getCallCount(), $matchingData)
            ]
        );
    }

    /**
     * Calculate forecasts for multiple time slots
     *
     * @param ForecastRequestDTO[] $requests
     * @return ForecastResultDTO[]
     */
    public function forecastMultiple(array $requests): array
    {
        $results = [];

        foreach ($requests as $request) {
            $results[] = $this->forecast($request);
        }

        return $results;
    }

    /**
     * Filter historical data to matching day-of-week and time-of-day
     *
     * @return HistoricalCallDataDTO[]
     */
    private function filterMatchingTimeSlots(ForecastRequestDTO $request): array
    {
        $targetDayOfWeek = (int) $request->getTargetDatetime()->format('N');
        $targetHour = (int) $request->getTargetDatetime()->format('H');
        $targetMinute = (int) $request->getTargetDatetime()->format('i');
        $queueName = $request->getQueueName();

        $matchingData = [];
        $cutoffDate = $request->getTargetDatetime()->modify('-' . $request->getLookbackWeeks() . ' weeks');

        foreach ($request->getHistoricalData() as $dataPoint) {
            // Filter by queue name
            if ($dataPoint->getQueueName() !== $queueName) {
                continue;
            }

            // Filter by day of week
            if ($dataPoint->getDayOfWeek() !== $targetDayOfWeek) {
                continue;
            }

            // Filter by time of day
            if ($dataPoint->getHourOfDay() !== $targetHour ||
                $dataPoint->getMinuteOfHour() !== $targetMinute) {
                continue;
            }

            // Filter by lookback period (only use data within N weeks)
            if ($dataPoint->getDatetime() < $cutoffDate) {
                continue;
            }

            // Must be before target date (can't use future data)
            if ($dataPoint->getDatetime() >= $request->getTargetDatetime()) {
                continue;
            }

            $matchingData[] = $dataPoint;
        }

        // Sort by date (most recent first)
        usort($matchingData, function($a, $b) {
            return $b->getDatetime() <=> $a->getDatetime();
        });

        // Limit to lookback weeks (in case we have more data)
        return array_slice($matchingData, 0, $request->getLookbackWeeks());
    }

    /**
     * Calculate simple moving average of call volumes
     */
    private function calculateMovingAverage(array $historicalData): float
    {
        if (empty($historicalData)) {
            return 0.0;
        }

        $sum = 0;
        foreach ($historicalData as $dataPoint) {
            $sum += $dataPoint->getCallCount();
        }

        return $sum / count($historicalData);
    }

    /**
     * Calculate average handle time across historical data
     */
    private function calculateAverageHandleTime(array $historicalData): float
    {
        if (empty($historicalData)) {
            return 0.0;
        }

        $sum = 0;
        foreach ($historicalData as $dataPoint) {
            $sum += $dataPoint->getAverageHandleTimeSeconds();
        }

        return $sum / count($historicalData);
    }

    /**
     * Calculate standard deviation of call volumes
     */
    private function calculateStandardDeviation(array $historicalData): float
    {
        if (count($historicalData) < 2) {
            return 0.0;
        }

        $mean = $this->calculateMovingAverage($historicalData);
        $squaredDifferences = 0;

        foreach ($historicalData as $dataPoint) {
            $diff = $dataPoint->getCallCount() - $mean;
            $squaredDifferences += $diff * $diff;
        }

        $variance = $squaredDifferences / count($historicalData);
        return sqrt($variance);
    }

    /**
     * Calculate required Full-Time Equivalent staff
     *
     * Formula:
     * workload = forecastedCalls × avgHandleTime (in seconds)
     * availableTime = timeSlotDuration (in seconds) × targetOccupancy
     * rawFTE = workload / availableTime
     * adjustedFTE = rawFTE / (1 - shrinkage)
     */
    private function calculateRequiredFTE(
        float $forecastedCalls,
        float $averageHandleTimeSeconds,
        ForecastRequestDTO $request
    ): float {
        if ($forecastedCalls <= 0) {
            return 0.0;
        }

        // Total workload in seconds
        $totalWorkloadSeconds = $forecastedCalls * $averageHandleTimeSeconds;

        // Available time per agent in this time slot
        $timeSlotDurationSeconds = $request->getTimeGranularityMinutes() * 60;
        $availableTimePerAgent = $timeSlotDurationSeconds * $request->getTargetOccupancy();

        // Raw FTE needed
        $rawFTE = $totalWorkloadSeconds / $availableTimePerAgent;

        // Adjust for shrinkage (breaks, meetings, training, etc.)
        $adjustedFTE = $rawFTE / (1 - $request->getShrinkageFactor());

        return $adjustedFTE;
    }

    /**
     * Calculate confidence interval bounds for FTE
     *
     * Uses standard deviation to create lower/upper bounds
     * Default: ±1.5 standard deviations (roughly 85% confidence)
     */
    private function calculateConfidenceIntervals(
        float $forecastedCalls,
        float $standardDeviation,
        float $averageHandleTimeSeconds,
        ForecastRequestDTO $request
    ): array {
        // Use standard deviation if available, otherwise use percentage
        if ($standardDeviation > 0) {
            // ±1.5 standard deviations
            $lowerCalls = max(0, $forecastedCalls - (1.5 * $standardDeviation));
            $upperCalls = $forecastedCalls + (1.5 * $standardDeviation);
        } else {
            // Fallback to percentage-based confidence interval
            $percentage = $request->getConfidenceIntervalPercentage();
            $lowerCalls = $forecastedCalls * (1 - $percentage);
            $upperCalls = $forecastedCalls * (1 + $percentage);
        }

        // Calculate FTE for lower and upper bounds
        $lowerFTE = $this->calculateRequiredFTE($lowerCalls, $averageHandleTimeSeconds, $request);
        $upperFTE = $this->calculateRequiredFTE($upperCalls, $averageHandleTimeSeconds, $request);

        return [$lowerFTE, $upperFTE];
    }

    /**
     * Create empty forecast when no historical data available
     */
    private function createEmptyForecast(ForecastRequestDTO $request): ForecastResultDTO
    {
        return new ForecastResultDTO(
            queueName: $request->getQueueName(),
            startTime: $request->getTargetDatetime(),
            endTime: $request->getTargetEndDatetime(),
            forecastedCalls: 0.0,
            averageHandleTimeSeconds: 0.0,
            requiredFTE: 0.0,
            confidenceLowerFTE: 0.0,
            confidenceUpperFTE: 0.0,
            dataPointsUsed: 0,
            standardDeviation: 0.0,
            metadata: [
                'algorithm' => 'moving_average',
                'warning' => 'No historical data available for this time slot'
            ]
        );
    }

    /**
     * Helper method to generate forecast requests for a full day
     *
     * @return ForecastRequestDTO[]
     */
    public static function generateDailyForecastRequests(
        string $queueName,
        \DateTimeImmutable $date,
        array $historicalData,
        int $startHour = 8,
        int $endHour = 17,
        int $granularityMinutes = 30,
        int $lookbackWeeks = 4
    ): array {
        $requests = [];
        $current = $date->setTime($startHour, 0);
        $end = $date->setTime($endHour, 0);

        while ($current < $end) {
            $requests[] = new ForecastRequestDTO(
                queueName: $queueName,
                targetDatetime: $current,
                historicalData: $historicalData,
                timeGranularityMinutes: $granularityMinutes,
                lookbackWeeks: $lookbackWeeks
            );

            $current = $current->add(new \DateInterval('PT' . $granularityMinutes . 'M'));
        }

        return $requests;
    }
}
