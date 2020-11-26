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
    protected array $input = [];

    protected array $options = [
        'startDate' => '2010-11-11',
        'dimensions' => ['ga:date'],
        'metrics' => [
            ['name' => 'ga:pageviews', 'type' => 'INTEGER'],
            ['name' => 'ga:avgPageLoadTime', 'type' => 'FLOAT'],
            ['name' => 'ga:avgSessionDuration', 'type' => 'TIME'],
        ],
    ];

    private array $dimensionHeaders;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dimensionHeaders = $this->options['dimensions'];
    }

    /** @test */
    public function defaultOptions(): void
    {
        $expected = [
            [
                'ga:date' => '2020-11-11',
                'ga:pageviews' => 2,
                'ga:avgPageLoadTime' => 2.2,
                'ga:avgSessionDuration' => 2200,
                'property' => 'www.example.com',
                'summary' => 'All Data',
            ],
            [
                'ga:date' => '2020-11-12',
                'ga:pageviews' => 3,
                'ga:avgPageLoadTime' => 3.3,
                'ga:avgSessionDuration' => 3300,
                'property' => 'www.example.com',
                'summary' => 'All Data',
            ],
            [
                'ga:date' => '2020-11-13',
                'ga:pageviews' => 5,
                'ga:avgPageLoadTime' => 5.5,
                'ga:avgSessionDuration' => 5500,
                'property' => 'www.example.com',
                'summary' => 'All Data',
            ],
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
