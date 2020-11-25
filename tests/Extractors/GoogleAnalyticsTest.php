<?php

/**
 * @author      Wizacha DevTeam <dev@wizacha.com>
 * @copyright   Copyright (c) Wizacha
 * @copyright   Copyright (c) Leonardo Marquine
 * @license     MIT
 */

declare(strict_types=1);

namespace PhpEtl\GoogleAnalytics\Tests\Extractors;

use PhpEtl\GoogleAnalytics\Tests\TestCase;
use PhpEtl\GoogleAnalytics\Extractors\GoogleAnalytics;
use Wizaplace\Etl\Row;

/**
 * Class GoogleAnalyticsTest
 *
 * @package Tests\Extractors
 */
class GoogleAnalyticsTest extends TestCase
{

    protected array $input = [];

    protected array $options = [
        'startDate' => '2010-11-11',
        'dimensions' => ['ga:date'],
        'metrics' => [['name' => 'ga:pageviews', 'type' => 'INTEGER']]
    ];

    /** @test */
    public function defaultOptions()
    {
        $expected = [
            new Row(['id' => 1, 'name' => 'John Doe', 'email' => 'johndoe@email.com']),
            new Row(['id' => 2, 'name' => 'Jane Doe', 'email' => 'janedoe@email.com']),
        ];

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

        $extractor = new GoogleAnalytics();
        $extractor->input($this->input);
        $extractor->options($this->options);
        $extractor->reportRequestSetup(
            $this->options['dimensions'],
            $this->options['metrics'],
            $this->options['startDate'],
            date('Y-m-d', strtotime('-1 day'))
        );

        $resourceReports = $this->prophesize(\Google_Service_AnalyticsReporting_Resource_Reports::class);
        $reportRequest = $extractor->reportRequest('default');
        print_r($reportRequest);
        $resourceReports->batchGet($reportRequest)->shouldBeCalled()->willReturn($this->getReportsResponse());

        $reportingService = $this->prophesize(\Google_Service_AnalyticsReporting::class);
        $reportingService->reports = $resourceReports->reveal();

        $extractor->setAnalyticsSvc($analyticsService->reveal())
            ->setReportingSvc($reportingService->reveal());

        static::assertEquals($expected, iterator_to_array($extractor->extract()));
    }

    private function getReportsResponse(): \Google_Service_AnalyticsReporting_GetReportsResponse
    {
        $columnHeader = new \Google_Service_AnalyticsReporting_ColumnHeader();
        $columnHeader->setDimensions(['ga:date']);
        $headerEntry = new \Google_Service_AnalyticsReporting_MetricHeaderEntry();
        $headerEntry->setName('ga:pageviews');
        $headerEntry->setType('INTEGER');
        $header = new \Google_Service_AnalyticsReporting_MetricHeader();
        $header->setMetricHeaderEntries([$headerEntry]);
        $columnHeader->setMetricHeader($header);

        $row = $this->prophesize(\Google_Service_AnalyticsReporting_ReportRow::class);
        $row->getDimensions()->willReturn(['2020-11-11']);
        $metrics = new \Google_Service_AnalyticsReporting_DateRangeValues();
        $metrics->setValues(100);
        $row->getMetrics()->willReturn([$metrics]);

        $reportData = $this->prophesize(\Google_Service_AnalyticsReporting_ReportData::class);
        $reportData->getRows()->willReturn([$row->reveal()]);

        $report = $this->prophesize(\Google_Service_AnalyticsReporting_Report::class);
        $report->getColumnHeader()->willReturn();
        $report->getData()->willReturn();

        $getRptsResponse = $this->prophesize(\Google_Service_AnalyticsReporting_GetReportsResponse::class);
        $getRptsResponse->getReports()->willReturn([$report->reveal()]);

        return $getRptsResponse->reveal();
    }
}
