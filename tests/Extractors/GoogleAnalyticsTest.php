<?php

/**
 * @author      Karl DeBisschop <kdebisschop@gmail.com>
 * @copyright   Copyright (c) Karl DeBisschop
 * @license     MIT
 */

declare(strict_types=1);

namespace PhpEtl\GoogleAnalytics\Tests\Extractors;

use Google_Service_AnalyticsReporting_GetReportsResponse as GetReportsResponse;
use PhpEtl\GoogleAnalytics\Extractors\GoogleAnalytics;
use PhpEtl\GoogleAnalytics\Tests\TestCase;

/**
 * Tests GoogleAnalytics.
 */
class GoogleAnalyticsTest extends TestCase
{
    private const GA_DATE = 'ga:date';
    private const GA_PAGE_VIEWS = 'ga:pageviews';
    private const GA_AVG_PAGE_LOAD_TIME = 'ga:avgPageLoadTime';
    private const GA_AVG_SESSION_DURATION = 'ga:avgSessionDuration';

    protected array $input = [];

    protected array $options = [
        'startDate' => '2010-11-11',
        'dimensions' => [self::GA_DATE],
        'metrics' => [
            ['name' => self::GA_PAGE_VIEWS, 'type' => 'INTEGER'],
            ['name' => self::GA_AVG_PAGE_LOAD_TIME, 'type' => 'FLOAT'],
            ['name' => self::GA_AVG_SESSION_DURATION, 'type' => 'TIME'],
        ],
    ];

    private array $dimensionHeaders;

    private string $profile = 'All Data';

    protected function setUp(): void
    {
        parent::setUp();
        $this->dimensionHeaders = $this->options['dimensions'];
    }

    /** @test */
    public function defaultOptions(): void
    {
        $expected = [
            $this->oneRow('2020-11-11', 2, 2.2, 2200),
            $this->oneRow('2020-11-12', 3, 3.3, 3300),
            $this->oneRow('2020-11-13', 5, 5.5, 5500),
        ];
        $extractor = new GoogleAnalytics();
        $extractor->input($this->input);
        $extractor->options($this->options);
        $extractor->setAnalyticsSvc($this->mockAnalyticsService())
            ->setReportingSvc($this->mockReportingService($this->mockReportResponse()));

        $i = 0;
        /** @var \Wizaplace\Etl\Row $row */
        foreach ($extractor->extract() as $row) {
            static::assertEquals($expected[$i++], ($row->toArray()));
        }
    }

    /** @test */
    public function skipView(): void
    {
        $extractor = new GoogleAnalytics();
        $extractor->input($this->input);
        $this->options['views'] = ['www.example.info'];
        $extractor->options($this->options);
        $extractor->setAnalyticsSvc($this->mockAnalyticsService())
            ->setReportingSvc($this->mockReportingService($this->mockReportResponse()));

        $i = 0;
        while ($extractor->extract()->valid()) {
            $extractor->extract()->next();
            ++$i;
        }
        static::assertEquals(0, $i);
    }

    public function skipProfile(): void
    {
        $extractor = new GoogleAnalytics();
        $extractor->input($this->input);
        $this->profile = 'Some Data';
        $extractor->options($this->options);
        $extractor->setAnalyticsSvc($this->mockAnalyticsService())
            ->setReportingSvc($this->mockReportingService($this->mockReportResponse()));

        $i = 0;
        while ($extractor->extract()->valid()) {
            $extractor->extract()->next();
            ++$i;
        }
        static::assertEquals(0, $i);
    }

    /** @test */
    public function noDimension(): void
    {
        $extractor = new GoogleAnalytics();
        unset($this->options['dimensions']);
        static::expectException(\InvalidArgumentException::class);
        $extractor->options($this->options);
    }

    /** @test */
    public function tooManyDimensions(): void
    {
        $extractor = new GoogleAnalytics();
        $this->options['dimensions'] = ['1', '2', '3', '4', '5', '6', '7', '8'];
        static::expectException(\InvalidArgumentException::class);
        $extractor->options($this->options);
    }

    /** @test */
    public function noMetrics(): void
    {
        $extractor = new GoogleAnalytics();
        unset($this->options['metrics']);
        static::expectException(\InvalidArgumentException::class);
        $extractor->options($this->options);
    }

    /** @test */
    public function tooManyMetrics(): void
    {
        $extractor = new GoogleAnalytics();
        $this->options['metrics'] = range(1, 11);
        static::expectException(\InvalidArgumentException::class);
        $extractor->options($this->options);
    }

    /** @test */
    public function noStartDate(): void
    {
        $extractor = new GoogleAnalytics();
        unset($this->options['startDate']);
        static::expectException(\InvalidArgumentException::class);
        $extractor->options($this->options);
    }

    private function oneRow(string $date, int $pages, float $time, int $duration): array
    {
        return [
            self::GA_DATE => $date,
            self::GA_PAGE_VIEWS => $pages,
            self::GA_AVG_PAGE_LOAD_TIME => $time,
            self::GA_AVG_SESSION_DURATION => $duration,
            'property' => 'www.example.com',
            'summary' => $this->profile,
        ];
    }

    private function mockReportRow(array $dimensions, array $values): \Google_Service_AnalyticsReporting_ReportRow
    {
        $row = new \Google_Service_AnalyticsReporting_ReportRow();
        $row->setDimensions($dimensions);
        $metrics = new \Google_Service_AnalyticsReporting_DateRangeValues();
        $metrics->setValues($values);
        $row->setMetrics([$metrics]);

        return $row;
    }

    private function mockReport(): \Google_Service_AnalyticsReporting_Report
    {
        $report = new \Google_Service_AnalyticsReporting_Report();
        $reportData = new \Google_Service_AnalyticsReporting_ReportData();
        $rows = [
            $this->mockReportRow(['2020-11-11'], [2, 2.2, 2200]),
            $this->mockReportRow(['2020-11-12'], [3, 3.3, 3300]),
            $this->mockReportRow(['2020-11-13'], [5, 5.5, 5500]),
        ];
        $reportData->setRows($rows);
        $report->setData($reportData);
        $columnHeader = new \Google_Service_AnalyticsReporting_ColumnHeader();
        $columnHeader->setDimensions($this->dimensionHeaders);
        $metricHeader = new \Google_Service_AnalyticsReporting_MetricHeader();
        $metricHeaderEntries = [];
        foreach ($this->options['metrics'] as $metric) {
            $metricHeaderEntry = new \Google_Service_AnalyticsReporting_MetricHeaderEntry();
            $metricHeaderEntry->setName($metric['name']);
            $metricHeaderEntry->setType($metric['type']);
            $metricHeaderEntries[] = $metricHeaderEntry;
        }
        $metricHeader->setMetricHeaderEntries($metricHeaderEntries);
        $columnHeader->setMetricHeader($metricHeader);
        $report->setColumnHeader($columnHeader);

        return $report;
    }

    private function mockAnalyticsService(): \Google_Service_Analytics
    {
        $profile = $this->prophesize(\Google_Service_Analytics_ProfileSummary::class);
        $profile->getId()->willReturn('12345');
        $profile->getName()->willReturn('All Data');

        $propertySummary = $this->prophesize(\Google_Service_Analytics_WebPropertySummary::class);
        $propertySummary->getName()->willReturn('www.example.com');
        $propertySummary->getProfiles()->willReturn([$profile->reveal()]);

        $accountSummary = $this->prophesize(\Google_Service_Analytics_AccountSummary::class);
        $accountSummary->getWebProperties()->willReturn([$propertySummary->reveal()]);

        $accountSummaries = $this->prophesize(\Google_Service_Analytics_AccountSummaries::class);
        $accountSummaries->getItems()->willReturn([$accountSummary->reveal()]);

        $mgmtAcctSummary = $this->prophesize(\Google_Service_Analytics_Resource_ManagementAccountSummaries::class);
        $mgmtAcctSummary->listManagementAccountSummaries()->willReturn($accountSummaries->reveal());

        $analyticsService = $this->prophesize(\Google_Service_Analytics::class);
        $analyticsService->management_accountSummaries = $mgmtAcctSummary->reveal();

        return $analyticsService->reveal();
    }

    private function mockReportResponse(): GetReportsResponse
    {
        $response = new GetReportsResponse();
        $response->setReports([$this->mockReport()]);

        return $response;
    }

    private function mockReportingService(GetReportsResponse $response): \Google_Service_AnalyticsReporting
    {
        $mock = $this->createMock(\Google_Service_AnalyticsReporting_Resource_Reports::class);
        $mock->method('batchGet')->willReturn($response);

        $client = $this->prophesize(\Google_Client::class);
        $reportingService = new \Google_Service_AnalyticsReporting($client->reveal());
        $reportingService->reports = $mock;

        return $reportingService;
    }
}
