<?php

namespace App\Tests\Forecasting;

use App\Forecasting\ForecastRequestDTO;
use App\Forecasting\HistoricalCallDataDTO;

/**
 * Test data builder for creating forecast test fixtures
 */
class ForecastingTestDataBuilder
{
    /**
     * Create historical call data record
     */
    public static function createHistoricalCallData(
        string $queueName,
        \DateTimeImmutable $datetime,
        int $callCount,
        float $averageHandleTimeSeconds = 300.0
    ): HistoricalCallDataDTO {
        return new HistoricalCallDataDTO(
            queueName: $queueName,
            datetime: $datetime,
            callCount: $callCount,
            averageHandleTimeSeconds: $averageHandleTimeSeconds
        );
    }

    /**
     * Create multiple weeks of historical data for the same time slot
     *
     * @param int[] $callCounts Array of call counts for each week
     * @return HistoricalCallDataDTO[]
     */
    public static function createWeeklyHistoricalData(
        string $queueName,
        \DateTimeImmutable $baseDate,
        array $callCounts,
        float $avgHandleTime = 300.0
    ): array {
        $historicalData = [];

        foreach ($callCounts as $weekOffset => $callCount) {
            $date = $baseDate->modify("-{$weekOffset} weeks");
            $historicalData[] = self::createHistoricalCallData(
                $queueName,
                $date,
                $callCount,
                $avgHandleTime
            );
        }

        return $historicalData;
    }

    /**
     * Create a full day of historical data with varying call volumes
     *
     * @return HistoricalCallDataDTO[]
     */
    public static function createDailyHistoricalData(
        string $queueName,
        \DateTimeImmutable $date,
        int $startHour = 8,
        int $endHour = 17,
        int $granularityMinutes = 30,
        int $baseCallVolume = 40
    ): array {
        $historicalData = [];
        $current = $date->setTime($startHour, 0);
        $end = $date->setTime($endHour, 0);

        while ($current < $end) {
            // Simulate peak hours (10-12, 14-16)
            $hour = (int) $current->format('H');
            $peakFactor = 1.0;

            if (($hour >= 10 && $hour < 12) || ($hour >= 14 && $hour < 16)) {
                $peakFactor = 1.3; // 30% more calls during peak
            } elseif ($hour >= 12 && $hour < 14) {
                $peakFactor = 0.7; // 30% fewer calls during lunch
            }

            $callCount = (int) round($baseCallVolume * $peakFactor);
            $handleTime = 280 + rand(0, 40); // Vary 280-320 seconds

            $historicalData[] = self::createHistoricalCallData(
                $queueName,
                $current,
                $callCount,
                $handleTime
            );

            $current = $current->add(new \DateInterval('PT' . $granularityMinutes . 'M'));
        }

        return $historicalData;
    }

    /**
     * Create multiple weeks of daily historical data
     *
     * @return HistoricalCallDataDTO[]
     */
    public static function createMultiWeekHistoricalData(
        string $queueName,
        \DateTimeImmutable $startDate,
        int $numberOfWeeks = 4,
        int $daysPerWeek = 5
    ): array {
        $historicalData = [];

        for ($week = 0; $week < $numberOfWeeks; $week++) {
            for ($day = 0; $day < $daysPerWeek; $day++) {
                $date = $startDate->modify("-{$week} weeks")->modify("+{$day} days");
                $dailyData = self::createDailyHistoricalData($queueName, $date);
                $historicalData = array_merge($historicalData, $dailyData);
            }
        }

        return $historicalData;
    }

    /**
     * Create forecast request with standard parameters
     */
    public static function createForecastRequest(
        string $queueName,
        \DateTimeImmutable $targetDatetime,
        array $historicalData,
        int $lookbackWeeks = 4,
        int $granularityMinutes = 30
    ): ForecastRequestDTO {
        return new ForecastRequestDTO(
            queueName: $queueName,
            targetDatetime: $targetDatetime,
            historicalData: $historicalData,
            timeGranularityMinutes: $granularityMinutes,
            lookbackWeeks: $lookbackWeeks
        );
    }

    /**
     * Create historical data with specific pattern for testing filtering
     *
     * @return HistoricalCallDataDTO[]
     */
    public static function createPatternedHistoricalData(
        \DateTimeImmutable $targetDate
    ): array {
        $data = [];

        // Create data for 4 weeks back, multiple days and times
        for ($week = 1; $week <= 4; $week++) {
            for ($day = 1; $day <= 7; $day++) {
                for ($hour = 8; $hour <= 17; $hour++) {
                    $date = $targetDate
                        ->modify("-{$week} weeks")
                        ->modify(sprintf("%+d days", $day - (int)$targetDate->format('N')))
                        ->setTime($hour, 0);

                    // Different patterns for different queues
                    $data[] = new HistoricalCallDataDTO(
                        queueName: 'sales',
                        datetime: $date,
                        callCount: 40 + $week + $hour,
                        averageHandleTimeSeconds: 300
                    );

                    $data[] = new HistoricalCallDataDTO(
                        queueName: 'support',
                        datetime: $date,
                        callCount: 30 + $week + $hour,
                        averageHandleTimeSeconds: 400
                    );
                }
            }
        }

        return $data;
    }

    /**
     * Create historical data with known statistical properties
     *
     * @return HistoricalCallDataDTO[]
     */
    public static function createStatisticalHistoricalData(
        string $queueName,
        \DateTimeImmutable $baseDate,
        float $mean,
        float $stdDev,
        int $numWeeks = 4
    ): array {
        $data = [];

        // Generate data points with specific mean and std dev
        // Using simple variance: [mean-2*std, mean-std, mean, mean+std, mean+2*std]
        $values = [
            $mean - 2 * $stdDev,
            $mean - $stdDev,
            $mean,
            $mean + $stdDev,
        ];

        for ($i = 0; $i < $numWeeks && $i < count($values); $i++) {
            $date = $baseDate->modify("-" . ($i + 1) . " weeks");
            $data[] = new HistoricalCallDataDTO(
                queueName: $queueName,
                datetime: $date,
                callCount: (int) round($values[$i]),
                averageHandleTimeSeconds: 300
            );
        }

        return $data;
    }
}
