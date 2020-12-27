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
use PhpEtl\GoogleAnalytics\Extractors\Request;
use PhpEtl\GoogleAnalytics\Tests\TestCase;
use PHPUnit\Framework\MockObject\Matcher;
use Prophecy\Argument;
use Wizaplace\Etl\Extractors\Extractor;

/**
 * Tests GoogleAnalytics.
 *
 * @coversDefaultClass \PhpEtl\GoogleAnalytics\Extractors\GoogleAnalytics
 *
 * @covers ::__construct
 * @covers ::extract
 * @covers ::getProfiles
 * @covers ::getRowData
 * @covers ::isWantedProperty
 * @covers ::isWantedView
 * @covers ::options
 * @covers ::setAnalyticsSvc
 * @covers ::setReportRequest
 * @covers ::setReportingSvc
 * @covers ::validate
 * @uses \PhpEtl\GoogleAnalytics\Extractors\GoogleAnalytics::delay
 * @covers ::reportRequest
 * @covers ::reportRequestSetup
 * @uses \PhpEtl\GoogleAnalytics\Extractors\GoogleAnalytics::setHeaders
 * @uses \PhpEtl\GoogleAnalytics\Extractors\Request::dateRange
 * @uses \PhpEtl\GoogleAnalytics\Extractors\Request::dimensions
 * @uses \PhpEtl\GoogleAnalytics\Extractors\Request::metrics
 *
 */
class GoogleAnalyticsTest extends TestCase
{
    private const GA_DATE = 'ga:date';
    private const GA_PAGE_VIEWS = 'ga:pageviews';
    private const GA_AVG_PAGE_LOAD_TIME = 'ga:avgPageLoadTime';
    private const GA_AVG_SESSION_DURATION = 'ga:avgSessionDuration';

    protected array $input = [];

    protected array $options = [
        'startDate' => '2020-11-01',
        'endDate' => '2020-12-31',
        'dimensions' => [self::GA_DATE],
        'metrics' => [
            ['name' => self::GA_PAGE_VIEWS, 'type' => 'INTEGER'],
            ['name' => self::GA_AVG_PAGE_LOAD_TIME, 'type' => 'FLOAT'],
            ['name' => self::GA_AVG_SESSION_DURATION, 'type' => 'TIME'],
        ],
    ];

    private array $dimensionHeaders;

    private string $profile = 'All Data';

    private string $site = 'www.example.com';

    private GoogleAnalytics $extractor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dimensionHeaders = $this->options['dimensions'];
        $this->extractor = new GoogleAnalytics();
        $this->extractor->setAnalyticsSvc($this->mockAnalyticsService())
            ->setReportingSvc($this->mockReportingService($this->mockReportResponse()));
        $this->extractor->input($this->input);
    }

    /**
     * @test
     */
    public function defaultOptions(): void
    {
        $this->extractor->setReportRequest($this->mockReportingRequest());
        $expected = [
            $this->oneRow('2020-11-11', 2, 2.2, 2200),
            $this->oneRow('2020-11-12', 3, 3.3, 3300),
            $this->oneRow('2020-11-13', 5, 5.5, 5500),
        ];
        $this->options['properties'] = [$this->site];
        $this->extractor->options($this->options);

        $i = 0;
        /** @var \Wizaplace\Etl\Row $row */
        foreach ($this->extractor->extract() as $row) {
            static::assertEquals($expected[$i++], ($row->toArray()));
        }
        static::assertEquals(3, $i);
    }

    /**
     * @test
     */
    public function allViews(): void
    {
        $this->extractor->setReportRequest($this->mockReportingRequest());
        $this->options['properties'] = [$this->site];
        $this->extractor->options($this->options);

        $i = 0;
        $iterator = $this->extractor->extract();
        while ($iterator->valid()) {
            $iterator->next();
            ++$i;
        }
        static::assertEquals(3, $i);
    }

    /**
     * @test
     */
    public function includeView(): void
    {
        $this->extractor->setReportRequest($this->mockReportingRequest());
        $this->options['views'] = [$this->profile, 'Some Data'];
        $this->options['properties'] = [$this->site];
        $this->extractor->options($this->options);

        $i = 0;
        $iterator = $this->extractor->extract();
        while ($iterator->valid()) {
            $iterator->next();
            ++$i;
        }
        static::assertEquals(3, $i);
    }

    /**
     * @test
     */
    public function skipView(): void
    {
        $this->options['views'] = ['Some Data'];
        $this->extractor->options($this->options);

        $i = 0;
        $iterator = $this->extractor->extract();
        while ($iterator->valid()) {
            $iterator->next();
            ++$i;
        }
        static::assertEquals(0, $i);
    }

    /**
     * @test
     */
    public function allPropertiesViews(): void
    {
        $this->extractor->setReportRequest($this->mockReportingRequest());
        $this->options['properties'] = [$this->site, 'not-a-site.example.com'];
        $this->options['views'] = [$this->profile];
        $this->extractor->options($this->options);

        $i = 0;
        $iterator = $this->extractor->extract();
        while ($iterator->valid()) {
            $iterator->next();
            ++$i;
        }
        static::assertEquals(6, $i);
    }

    /**
     * @test
     */
    public function includeProperty(): void
    {
        $this->extractor->setReportRequest($this->mockReportingRequest());
        $this->options['properties'] = ['www.example.info', $this->site];
        $this->extractor->options($this->options);

        $i = 0;
        $iterator = $this->extractor->extract();
        while ($iterator->valid()) {
            $iterator->next();
            ++$i;
        }
        static::assertEquals(3, $i);
    }

    /**
     * @test
     */
    public function skipProperty(): void
    {
        $this->options['properties'] = ['www.example.info', 'www.example.org'];
        $this->extractor->options($this->options);

        $i = 0;
        $iterator = $this->extractor->extract();
        while ($iterator->valid()) {
            $iterator->next();
            ++$i;
        }
        static::assertEquals(0, $i);
    }

    /**
     * @test
     */
    public function noDimension(): void
    {
        unset($this->options['dimensions']);
        static::expectException(\InvalidArgumentException::class);
        $this->extractor->options($this->options);
    }

    /**
     * @test
     */
    public function oneDimension(): void
    {
        static::assertInstanceOf(Extractor::class, $this->extractor->options($this->options));
    }

    /**
     * @test
     */
    public function sevenDimensions(): void
    {
        $this->options['dimensions'] = range(1, 7);
        static::assertInstanceOf(Extractor::class, $this->extractor->options($this->options));
    }

    /**
     * @test
     */
    public function tooManyDimensions(): void
    {
        $this->options['dimensions'] = range(1, 8);
        static::expectException(\InvalidArgumentException::class);
        $this->extractor->options($this->options);
    }

    /**
     * @test
     */
    public function noMetrics(): void
    {
        unset($this->options['metrics']);
        static::expectException(\InvalidArgumentException::class);
        $this->extractor->options($this->options);
    }

    /**
     * @test
     */
    public function oneMetric(): void
    {
        $this->options['metrics'] = [self::GA_AVG_PAGE_LOAD_TIME];
        static::assertInstanceOf(Extractor::class, $this->extractor->options($this->options));
    }

    /**
     * @test
     */
    public function tenMetrics(): void
    {
        $this->options['metrics'] = range(1, 10);
        static::assertInstanceOf(Extractor::class, $this->extractor->options($this->options));
    }

    /**
     * @test
     */
    public function tooManyMetrics(): void
    {
        $this->options['metrics'] = range(1, 11);
        static::expectException(\InvalidArgumentException::class);
        $this->extractor->options($this->options);
    }

    /**
     * @test
     */
    public function noStartDate(): void
    {
        unset($this->options['startDate']);
        static::expectException(\InvalidArgumentException::class);
        $this->extractor->options($this->options);
    }

    private function oneRow(string $date, int $pages, float $time, int $duration): array
    {
        return [
            self::GA_DATE => $date,
            self::GA_PAGE_VIEWS => $pages,
            self::GA_AVG_PAGE_LOAD_TIME => $time,
            self::GA_AVG_SESSION_DURATION => $duration,
            'property' => $this->site,
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
        $profile->getName()->willReturn($this->profile);

        $secondProfile = $this->prophesize(\Google_Service_Analytics_ProfileSummary::class);
        $secondProfile->getId()->willReturn('123456');
        $secondProfile->getName()->willReturn('No Data');

        $propertySummary = $this->prophesize(\Google_Service_Analytics_WebPropertySummary::class);
        $propertySummary->getName()->willReturn($this->site);
        $propertySummary->getProfiles()->willReturn([$profile->reveal()]);

        $secondProperty = $this->prophesize(\Google_Service_Analytics_WebPropertySummary::class);
        $secondProperty->getName()->willReturn('not-a-site.example.com');
        $secondProperty->getProfiles()->willReturn([$secondProfile->reveal(), $profile->reveal()]);

        $accountSummary = $this->prophesize(\Google_Service_Analytics_AccountSummary::class);
        $accountSummary->getWebProperties()->willReturn([$propertySummary->reveal(), $secondProperty->reveal()]);

        $accountSummaries = $this->prophesize(\Google_Service_Analytics_AccountSummaries::class);
        $accountSummaries->getItems()->willReturn([$accountSummary->reveal()]);

        $mgmtAcctSummary = $this->prophesize(\Google_Service_Analytics_Resource_ManagementAccountSummaries::class);
        $mgmtAcctSummary->listManagementAccountSummaries()->willReturn($accountSummaries->reveal());

        $analyticsService = $this->prophesize(\Google_Service_Analytics::class);
        $return = $analyticsService->reveal();
        $return->management_accountSummaries = $mgmtAcctSummary->reveal();

        return $return;
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

    private function mockReportingRequest(): \Google_Service_AnalyticsReporting_ReportRequest
    {
        $mock = $this->prophesize(\Google_Service_AnalyticsReporting_ReportRequest::class);
        $mock->setPageSize(1000)->shouldBeCalled();
        $mock->setDateRanges(Request::dateRange('2020-11-01', '2020-12-31'))->shouldBeCalled();
        $mock->setDimensions(Request::dimensions(['ga:date']))->shouldBeCalled();
        $mock->setMetrics(Request::metrics($this->options['metrics']))->shouldBeCalled();
        $mock->setIncludeEmptyRows(true)->shouldBeCalled();
        $mock->setViewId(Argument::any())->shouldBeCalled();
        return $mock->reveal();
    }
}
