<?php

namespace App\Tests\Forecasting;

use App\Forecasting\ForecastRequestDTO;
use App\Forecasting\HistoricalCallDataDTO;
use App\Forecasting\MovingAverageForecaster;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for MovingAverageForecaster
 */
class MovingAverageForecasterTest extends TestCase
{
    private MovingAverageForecaster $forecaster;

    protected function setUp(): void
    {
        parent::setUp();
        $this->forecaster = new MovingAverageForecaster();
    }

    /**
     * Test basic forecast with simple moving average
     */
    public function testBasicForecast(): void
    {
        $targetDate = new \DateTimeImmutable('2025-12-18 09:00:00'); // Wednesday

        // Historical data: 4 weeks of Wednesday 09:00 data
        $historicalData = ForecastingTestDataBuilder::createWeeklyHistoricalData(
            queueName: 'sales',
            baseDate: $targetDate,
            callCounts: [1 => 50, 2 => 48, 3 => 52, 4 => 46], // Keys are weeks back
            avgHandleTime: 300
        );

        $request = ForecastingTestDataBuilder::createForecastRequest(
            queueName: 'sales',
            targetDatetime: $targetDate,
            historicalData: $historicalData,
            lookbackWeeks: 4
        );

        $result = $this->forecaster->forecast($request);

        // Expected average: (50 + 48 + 52 + 46) / 4 = 49
        $this->assertEquals(49.0, $result->getForecastedCalls());
        $this->assertEquals(49, $result->getForecastedCallsRounded());
        $this->assertEquals(300.0, $result->getAverageHandleTimeSeconds());
        $this->assertEquals(4, $result->getDataPointsUsed());
        $this->assertEquals('sales', $result->getQueueName());
    }

    /**
     * Test that only matching day-of-week data is used
     */
    public function testFiltersByDayOfWeek(): void
    {
        $targetDate = new \DateTimeImmutable('2025-12-17 09:00:00'); // Wednesday

        $historicalData = [];

        // Add Wednesday data (should match)
        for ($week = 1; $week <= 4; $week++) {
            $date = $targetDate->modify("-{$week} weeks");
            $historicalData[] = new HistoricalCallDataDTO('sales', $date, 50, 300);
        }

        // Add Thursday data (should NOT match)
        for ($week = 1; $week <= 4; $week++) {
            $date = $targetDate->modify("-{$week} weeks")->modify('+1 day');
            $historicalData[] = new HistoricalCallDataDTO('sales', $date, 100, 300); // Different volume
        }

        $request = ForecastingTestDataBuilder::createForecastRequest(
            queueName: 'sales',
            targetDatetime: $targetDate,
            historicalData: $historicalData
        );

        $result = $this->forecaster->forecast($request);

        // Should only use Wednesday data: (50+50+50+50)/4 = 50
        $this->assertEquals(50.0, $result->getForecastedCalls());
        $this->assertEquals(4, $result->getDataPointsUsed(), 'Should only use Wednesday data');
    }

    /**
     * Test that only matching time-of-day data is used
     */
    public function testFiltersByTimeOfDay(): void
    {
        $targetDate = new \DateTimeImmutable('2025-12-17 09:00:00');

        $historicalData = [];

        // Add 09:00 data (should match)
        for ($week = 1; $week <= 4; $week++) {
            $date = $targetDate->modify("-{$week} weeks");
            $historicalData[] = new HistoricalCallDataDTO('sales', $date, 50, 300);
        }

        // Add 10:00 data (should NOT match)
        for ($week = 1; $week <= 4; $week++) {
            $date = $targetDate->modify("-{$week} weeks")->setTime(10, 0);
            $historicalData[] = new HistoricalCallDataDTO('sales', $date, 100, 300);
        }

        $request = ForecastingTestDataBuilder::createForecastRequest(
            queueName: 'sales',
            targetDatetime: $targetDate,
            historicalData: $historicalData
        );

        $result = $this->forecaster->forecast($request);

        // Should only use 09:00 data
        $this->assertEquals(50.0, $result->getForecastedCalls());
        $this->assertEquals(4, $result->getDataPointsUsed());
    }

    /**
     * Test that only matching queue data is used
     */
    public function testFiltersByQueueName(): void
    {
        $targetDate = new \DateTimeImmutable('2025-12-17 09:00:00');

        $historicalData = [];

        // Add sales queue data (should match)
        for ($week = 1; $week <= 4; $week++) {
            $date = $targetDate->modify("-{$week} weeks");
            $historicalData[] = new HistoricalCallDataDTO('sales', $date, 50, 300);
        }

        // Add support queue data (should NOT match)
        for ($week = 1; $week <= 4; $week++) {
            $date = $targetDate->modify("-{$week} weeks");
            $historicalData[] = new HistoricalCallDataDTO('support', $date, 100, 300);
        }

        $request = ForecastingTestDataBuilder::createForecastRequest(
            queueName: 'sales',
            targetDatetime: $targetDate,
            historicalData: $historicalData
        );

        $result = $this->forecaster->forecast($request);

        // Should only use sales queue data
        $this->assertEquals(50.0, $result->getForecastedCalls());
        $this->assertEquals(4, $result->getDataPointsUsed());
    }

    /**
     * Test lookback period filtering
     */
    public function testRespectsLookbackPeriod(): void
    {
        $targetDate = new \DateTimeImmutable('2025-12-17 09:00:00');

        $historicalData = [];

        // Add data from 1-4 weeks ago (should match)
        for ($week = 1; $week <= 4; $week++) {
            $date = $targetDate->modify("-{$week} weeks");
            $historicalData[] = new HistoricalCallDataDTO('sales', $date, 50, 300);
        }

        // Add data from 5-8 weeks ago (should NOT match with lookback=4)
        for ($week = 5; $week <= 8; $week++) {
            $date = $targetDate->modify("-{$week} weeks");
            $historicalData[] = new HistoricalCallDataDTO('sales', $date, 100, 300);
        }

        $request = ForecastingTestDataBuilder::createForecastRequest(
            queueName: 'sales',
            targetDatetime: $targetDate,
            historicalData: $historicalData,
            lookbackWeeks: 4
        );

        $result = $this->forecaster->forecast($request);

        // Should only use last 4 weeks
        $this->assertEquals(50.0, $result->getForecastedCalls());
        $this->assertEquals(4, $result->getDataPointsUsed());
    }

    /**
     * Test that future data is excluded
     */
    public function testExcludesFutureData(): void
    {
        $targetDate = new \DateTimeImmutable('2025-12-17 09:00:00');

        $historicalData = [];

        // Add past data
        for ($week = 1; $week <= 4; $week++) {
            $date = $targetDate->modify("-{$week} weeks");
            $historicalData[] = new HistoricalCallDataDTO('sales', $date, 50, 300);
        }

        // Add future data (should be excluded)
        $futureDate = $targetDate->modify('+1 week');
        $historicalData[] = new HistoricalCallDataDTO('sales', $futureDate, 1000, 300);

        $request = ForecastingTestDataBuilder::createForecastRequest(
            queueName: 'sales',
            targetDatetime: $targetDate,
            historicalData: $historicalData
        );

        $result = $this->forecaster->forecast($request);

        // Should only use past data, excluding future
        $this->assertEquals(50.0, $result->getForecastedCalls());
        $this->assertEquals(4, $result->getDataPointsUsed());
    }

    /**
     * Test FTE calculation
     */
    public function testCalculatesRequiredFTE(): void
    {
        $targetDate = new \DateTimeImmutable('2025-12-17 09:00:00');

        // Create data with known values for predictable FTE calculation
        $historicalData = ForecastingTestDataBuilder::createWeeklyHistoricalData(
            queueName: 'sales',
            baseDate: $targetDate,
            callCounts: [1 => 60, 2 => 60, 3 => 60, 4 => 60],
            avgHandleTime: 300 // 5 minutes
        );

        $request = new ForecastRequestDTO(
            queueName: 'sales',
            targetDatetime: $targetDate,
            historicalData: $historicalData,
            timeGranularityMinutes: 30,
            lookbackWeeks: 4,
            shrinkageFactor: 0.25,
            targetOccupancy: 0.85
        );

        $result = $this->forecaster->forecast($request);

        // Verify calculation:
        // Forecasted calls: 60
        // Handle time: 300 seconds
        // Workload: 60 * 300 = 18,000 seconds
        // Available per agent: 30 min * 60 = 1,800 seconds
        // With occupancy: 1,800 * 0.85 = 1,530 seconds
        // Raw FTE: 18,000 / 1,530 = 11.76
        // With shrinkage: 11.76 / 0.75 = 15.69

        $this->assertGreaterThan(15.0, $result->getRequiredFTE());
        $this->assertLessThan(16.0, $result->getRequiredFTE());
        $this->assertEquals(16, $result->getRequiredAgents()); // Ceiling
    }

    /**
     * Test standard deviation calculation
     */
    public function testCalculatesStandardDeviation(): void
    {
        $targetDate = new \DateTimeImmutable('2025-12-17 09:00:00');

        // Create data with known variance
        // Values: [40, 50, 50, 60]
        // Mean: 50
        // Variance: [(40-50)² + (50-50)² + (50-50)² + (60-50)²] / 4 = [100+0+0+100]/4 = 50
        // StdDev: √50 ≈ 7.07
        $historicalData = ForecastingTestDataBuilder::createWeeklyHistoricalData(
            queueName: 'sales',
            baseDate: $targetDate,
            callCounts: [1 => 60, 2 => 50, 3 => 50, 4 => 40],
            avgHandleTime: 300
        );

        $request = ForecastingTestDataBuilder::createForecastRequest(
            queueName: 'sales',
            targetDatetime: $targetDate,
            historicalData: $historicalData
        );

        $result = $this->forecaster->forecast($request);

        $this->assertEquals(50.0, $result->getForecastedCalls());
        $this->assertGreaterThan(7.0, $result->getStandardDeviation());
        $this->assertLessThan(7.2, $result->getStandardDeviation());
    }

    /**
     * Test confidence intervals
     */
    public function testCalculatesConfidenceIntervals(): void
    {
        $targetDate = new \DateTimeImmutable('2025-12-17 09:00:00');

        $historicalData = ForecastingTestDataBuilder::createWeeklyHistoricalData(
            queueName: 'sales',
            baseDate: $targetDate,
            callCounts: [1 => 50, 2 => 48, 3 => 52, 4 => 46],
            avgHandleTime: 300
        );

        $request = ForecastingTestDataBuilder::createForecastRequest(
            queueName: 'sales',
            targetDatetime: $targetDate,
            historicalData: $historicalData
        );

        $result = $this->forecaster->forecast($request);

        // Confidence intervals should be around the required FTE
        $this->assertLessThan($result->getRequiredFTE(), $result->getConfidenceLowerFTE());
        $this->assertGreaterThan($result->getRequiredFTE(), $result->getConfidenceUpperFTE());
        $this->assertGreaterThan(0, $result->getConfidenceIntervalWidth());
    }

    /**
     * Test with no historical data
     */
    public function testWithNoHistoricalData(): void
    {
        $targetDate = new \DateTimeImmutable('2025-12-17 09:00:00');

        $request = ForecastingTestDataBuilder::createForecastRequest(
            queueName: 'sales',
            targetDatetime: $targetDate,
            historicalData: [] // No data
        );

        $result = $this->forecaster->forecast($request);

        $this->assertEquals(0.0, $result->getForecastedCalls());
        $this->assertEquals(0.0, $result->getRequiredFTE());
        $this->assertEquals(0, $result->getDataPointsUsed());
        $this->assertArrayHasKey('warning', $result->getMetadata());
    }

    /**
     * Test with no matching data (wrong queue)
     */
    public function testWithNoMatchingData(): void
    {
        $targetDate = new \DateTimeImmutable('2025-12-17 09:00:00');

        // Create data for different queue
        $historicalData = ForecastingTestDataBuilder::createWeeklyHistoricalData(
            queueName: 'support',
            baseDate: $targetDate,
            callCounts: [1 => 50, 2 => 48, 3 => 52, 4 => 46]
        );

        $request = ForecastingTestDataBuilder::createForecastRequest(
            queueName: 'sales', // Different queue
            targetDatetime: $targetDate,
            historicalData: $historicalData
        );

        $result = $this->forecaster->forecast($request);

        $this->assertEquals(0.0, $result->getForecastedCalls());
        $this->assertEquals(0, $result->getDataPointsUsed());
    }

    /**
     * Test with insufficient data (only 1 week)
     */
    public function testWithInsufficientData(): void
    {
        $targetDate = new \DateTimeImmutable('2025-12-17 09:00:00');

        // Only 1 week of data
        $historicalData = ForecastingTestDataBuilder::createWeeklyHistoricalData(
            queueName: 'sales',
            baseDate: $targetDate,
            callCounts: [1 => 50],
            avgHandleTime: 300
        );

        $request = ForecastingTestDataBuilder::createForecastRequest(
            queueName: 'sales',
            targetDatetime: $targetDate,
            historicalData: $historicalData,
            lookbackWeeks: 4
        );

        $result = $this->forecaster->forecast($request);

        // Should still calculate with available data
        $this->assertEquals(50.0, $result->getForecastedCalls());
        $this->assertEquals(1, $result->getDataPointsUsed());
        $this->assertEquals(0.0, $result->getStandardDeviation()); // Can't calc with n=1
    }

    /**
     * Test multiple forecasts
     */
    public function testForecastMultiple(): void
    {
        $targetDate = new \DateTimeImmutable('2025-12-17 09:00:00');

        // Create comprehensive historical data
        $historicalData = ForecastingTestDataBuilder::createPatternedHistoricalData($targetDate);

        // Create requests for multiple time slots
        $requests = [
            ForecastingTestDataBuilder::createForecastRequest('sales', $targetDate->setTime(9, 0), $historicalData),
            ForecastingTestDataBuilder::createForecastRequest('sales', $targetDate->setTime(10, 0), $historicalData),
            ForecastingTestDataBuilder::createForecastRequest('sales', $targetDate->setTime(11, 0), $historicalData),
        ];

        $results = $this->forecaster->forecastMultiple($requests);

        $this->assertCount(3, $results);
        $this->assertEquals(9, (int) $results[0]->getStartTime()->format('H'));
        $this->assertEquals(10, (int) $results[1]->getStartTime()->format('H'));
        $this->assertEquals(11, (int) $results[2]->getStartTime()->format('H'));
    }

    /**
     * Test daily forecast request generation
     */
    public function testGenerateDailyForecastRequests(): void
    {
        $date = new \DateTimeImmutable('2025-12-17');
        $historicalData = [];

        $requests = MovingAverageForecaster::generateDailyForecastRequests(
            queueName: 'sales',
            date: $date,
            historicalData: $historicalData,
            startHour: 8,
            endHour: 10,
            granularityMinutes: 30,
            lookbackWeeks: 4
        );

        // Should have 4 slots: 08:00, 08:30, 09:00, 09:30
        $this->assertCount(4, $requests);
        $this->assertEquals('08:00', $requests[0]->getTargetDatetime()->format('H:i'));
        $this->assertEquals('08:30', $requests[1]->getTargetDatetime()->format('H:i'));
        $this->assertEquals('09:00', $requests[2]->getTargetDatetime()->format('H:i'));
        $this->assertEquals('09:30', $requests[3]->getTargetDatetime()->format('H:i'));
    }

    /**
     * Test conversion to DemandForecastDTO
     */
    public function testConversionToDemandForecastDTO(): void
    {
        $targetDate = new \DateTimeImmutable('2025-12-17 09:00:00');

        $historicalData = ForecastingTestDataBuilder::createWeeklyHistoricalData(
            queueName: 'sales',
            baseDate: $targetDate,
            callCounts: [1 => 50, 2 => 48, 3 => 52, 4 => 46],
            avgHandleTime: 300
        );

        $request = ForecastingTestDataBuilder::createForecastRequest(
            queueName: 'sales',
            targetDatetime: $targetDate,
            historicalData: $historicalData
        );

        $result = $this->forecaster->forecast($request);
        $demandForecast = $result->toDemandForecastDTO();

        $this->assertInstanceOf(\App\Scheduling\DemandForecastDTO::class, $demandForecast);
        $this->assertEquals('sales', $demandForecast->getQueueName());
        $this->assertEquals(49, $demandForecast->getForecastedCalls());
        $this->assertEquals($result->getRequiredFTE(), $demandForecast->getRequiredFTE());
    }

    /**
     * Test different time granularities
     */
    public function testDifferentGranularities(): void
    {
        $targetDate = new \DateTimeImmutable('2025-12-17 09:00:00');

        $historicalData = ForecastingTestDataBuilder::createWeeklyHistoricalData(
            queueName: 'sales',
            baseDate: $targetDate,
            callCounts: [1 => 60, 2 => 60, 3 => 60, 4 => 60],
            avgHandleTime: 300
        );

        // Test 15-minute granularity
        $request15 = new ForecastRequestDTO(
            queueName: 'sales',
            targetDatetime: $targetDate,
            historicalData: $historicalData,
            timeGranularityMinutes: 15
        );

        $result15 = $this->forecaster->forecast($request15);

        // Test 60-minute granularity
        $request60 = new ForecastRequestDTO(
            queueName: 'sales',
            targetDatetime: $targetDate,
            historicalData: $historicalData,
            timeGranularityMinutes: 60
        );

        $result60 = $this->forecaster->forecast($request60);

        // Same forecast, but different FTE due to time slot duration
        $this->assertEquals($result15->getForecastedCalls(), $result60->getForecastedCalls());
        $this->assertNotEquals($result15->getRequiredFTE(), $result60->getRequiredFTE());

        // Longer time slots should require fewer FTE per slot
        $this->assertLessThan($result15->getRequiredFTE(), $result60->getRequiredFTE());
    }

    /**
     * Test metadata is populated
     */
    public function testMetadataIsPopulated(): void
    {
        $targetDate = new \DateTimeImmutable('2025-12-17 09:00:00');

        $historicalData = ForecastingTestDataBuilder::createWeeklyHistoricalData(
            queueName: 'sales',
            baseDate: $targetDate,
            callCounts: [1 => 50, 2 => 48, 3 => 52, 4 => 46],
            avgHandleTime: 300
        );

        $request = ForecastingTestDataBuilder::createForecastRequest(
            queueName: 'sales',
            targetDatetime: $targetDate,
            historicalData: $historicalData,
            lookbackWeeks: 4
        );

        $result = $this->forecaster->forecast($request);
        $metadata = $result->getMetadata();

        $this->assertArrayHasKey('algorithm', $metadata);
        $this->assertEquals('moving_average', $metadata['algorithm']);
        $this->assertArrayHasKey('lookback_weeks', $metadata);
        $this->assertEquals(4, $metadata['lookback_weeks']);
        $this->assertArrayHasKey('historical_calls', $metadata);
        $this->assertIsArray($metadata['historical_calls']);
    }

    /**
     * Test varying handle times are averaged correctly
     */
    public function testAveragesHandleTimes(): void
    {
        $targetDate = new \DateTimeImmutable('2025-12-17 09:00:00');

        $historicalData = [
            new HistoricalCallDataDTO('sales', $targetDate->modify('-1 week'), 50, 300),
            new HistoricalCallDataDTO('sales', $targetDate->modify('-2 weeks'), 50, 320),
            new HistoricalCallDataDTO('sales', $targetDate->modify('-3 weeks'), 50, 280),
            new HistoricalCallDataDTO('sales', $targetDate->modify('-4 weeks'), 50, 340),
        ];

        $request = ForecastingTestDataBuilder::createForecastRequest(
            queueName: 'sales',
            targetDatetime: $targetDate,
            historicalData: $historicalData
        );

        $result = $this->forecaster->forecast($request);

        // Average handle time: (300 + 320 + 280 + 340) / 4 = 310
        $this->assertEquals(310.0, $result->getAverageHandleTimeSeconds());
    }

    /**
     * Test shrinkage and occupancy factors affect FTE
     */
    public function testShrinkageAndOccupancyFactors(): void
    {
        $targetDate = new \DateTimeImmutable('2025-12-17 09:00:00');

        $historicalData = ForecastingTestDataBuilder::createWeeklyHistoricalData(
            queueName: 'sales',
            baseDate: $targetDate,
            callCounts: [1 => 60, 2 => 60, 3 => 60, 4 => 60],
            avgHandleTime: 300
        );

        // Test with high shrinkage
        $requestHighShrinkage = new ForecastRequestDTO(
            queueName: 'sales',
            targetDatetime: $targetDate,
            historicalData: $historicalData,
            shrinkageFactor: 0.40 // 40% shrinkage
        );

        $resultHighShrinkage = $this->forecaster->forecast($requestHighShrinkage);

        // Test with low shrinkage
        $requestLowShrinkage = new ForecastRequestDTO(
            queueName: 'sales',
            targetDatetime: $targetDate,
            historicalData: $historicalData,
            shrinkageFactor: 0.10 // 10% shrinkage
        );

        $resultLowShrinkage = $this->forecaster->forecast($requestLowShrinkage);

        // Higher shrinkage should require more FTE
        $this->assertGreaterThan(
            $resultLowShrinkage->getRequiredFTE(),
            $resultHighShrinkage->getRequiredFTE()
        );
    }
}
