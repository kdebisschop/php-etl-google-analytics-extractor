<?php

/**
 * @author      Karl DeBisschop <kdebisschop@gmail.com>
 * @copyright   Copyright (c) Karl DeBisschop
 * @license     MIT
 */

declare(strict_types=1);

namespace PhpEtl\GoogleAnalytics\Extractors;

use Google\Exception as GoogleException;
use Wizaplace\Etl\Extractors\Extractor;
use Wizaplace\Etl\Row;
use Wizaplace\Etl\Step;

/**
 * Extract statistics from multiple properties in GoogleAnalytics.
 *
 * ```php
 * use \Wizaplace\Etl\Etl;
 * use \Etl\GoogleAnalytics\Extractors\GoogleAnalytics;
 *
 * $pipeline = new Etl();
 * $config = '/path/to/credentials.json';
 * $extractor = new GoogleAnalytics($config);
 * $input = ['example.com', 'example.org'];
 * $options = [
 *     'startDate' => '2020-11-11',
 *     'dimensions' => ['ga:date'],
 *     'metrics' => [
 *         ['name' => 'ga:pageviews', 'type' => 'INTEGER'],
 *         ['name' => 'ga:uniquePageviews', 'type' => 'INTEGER'],
 *         ['name' => 'ga:searchResultViews', 'type' => 'INTEGER'],
 *         ['name' => 'ga:users', 'type' => 'INTEGER'],
 *         ['name' => 'ga:newUsers', 'type' => 'INTEGER'],
 *         ['name' => 'ga:sessions', 'type' => 'INTEGER'],
 *         ['name' => 'ga:avgPageLoadTime', 'type' => 'FLOAT'],
 *         ['name' => 'ga:avgRedirectionTime', 'type' => 'FLOAT'],
 *         ['name' => 'ga:avgServerResponseTime', 'type' => 'FLOAT'],
 *         ['name' => 'ga:avgSessionDuration', 'type' => 'TIME'],
 *     ],
 *     'views' => [],
 * ];
 * $iterator = $pipeline->extract($extractor, $input, $options)->toIterator();
 * ```
 *
 * Set $input to an empty array to extract all sites associated with the
 * profile in $config.
 *
 * Set $options['views'] to a non-empty array of profile summary names to
 * extract only the specified views of the data.
 *
 * ### Input
 *
 * By default, the input is all the web properties the Google client has access to.
 * However, an array of property names may be specified to limit the extraction to
 * a smaller set of properties. (In the example below, property names correspond to
 * the web site name, but that is not necessarily true for all GA users.)
 *
 * ```php
 * $input = ['www.example.com', 'demo.example.com'];
 * ```
 *
 * ### Options
 *  - Dimensions (required) {@see GoogleAnalytics::$dimensions}
 *  - Metrics (required) {@see GoogleAnalytics::$metrics}
 *  - StartDate (required) {@see GoogleAnalytics::$startDate}
 *  - EndDate (optional) {@see GoogleAnalytics::$endDate}
 *  - Views (optional) {@see GoogleAnalytics::$views}
 */
class GoogleAnalytics extends Extractor
{
    /**
     * @var array<string>
     */
    protected $availableOptions = ['startDate', 'endDate', 'views', 'properties', 'dimensions', 'metrics'];

    /**
     * The dimension or dimensions used to group analytics data (frequently "ga:date").
     *
     * ```php
     * $options = ['dimensions' => ['ga:date']];
     * ```
     *
     * @var string[]
     */
    protected array $dimensions = [];

    /**
     * The last date to be extracted. Uses yesterday if none is explicitly provided.
     *
     * ```php
     * $options = ['startDate' => '2020-11-11'];
     * ```
     */
    protected string $endDate = '';

    /**
     * The metrics to be extracted.
     *
     * Each element of the metrics array consists of a name and a type:
     *
     * ```php
     * $options = [
     *     'metrics' => [
     *         ['name' => 'ga:pageviews', 'type' => 'INTEGER'],
     *         ['name' => 'ga:avgPageLoadTime', 'type' => 'FLOAT'],
     *         ['name' => 'ga:avgSessionDuration', 'type' => 'TIME'],
     *     ]
     * ];
     * ```
     *
     * @var array[][]
     */
    protected array $metrics = [];

    /**
     * The first date to be extracted.
     *
     * ```php
     * $options = ['startDate' => '2020-11-11'];
     * ```
     */
    protected string $startDate = '';

    /**
     * If specified, views will select specific profile summaries for extraction.
     *
     * ```php
     * $options = ['views' => ['All data']];
     * ```
     *
     * @var string[]
     */
    protected array $views = [];

    /**
     * If specified, properties will limit the specific GA properties to extract.
     *
     * ```php
     * $options = ['properties' => ['www.example.com']];
     * ```
     *
     * @var string[]
     */
    protected array $properties = [];

    protected \Closure $delayFunction;

    /**
     * @var string[]
     */
    private array $dimensionHeaders;

    /**
     * @var string[]
     */
    private array $metricHeaders;

    private int $clientReqCount = 0;

    protected int $oneSecond = 1000000;

    protected \Google_Service_Analytics $analyticsService;

    protected \Google_Service_AnalyticsReporting $reportingService;

    protected \Google_Service_AnalyticsReporting_ReportRequest $reportRequest;

    /**
     * Creates a new Google Analytics Extractor instance.
     *
     * Set the auth config from new or deprecated JSON config. This structure
     * should match the file downloaded from the "Download JSON" button on in
     * the Google Developer Console. If $config is not set in the constructor,
     * setters for analytics service and analytics reporting service must be
     * used to inject the required dependencies.
     *
     * @param string $config The configuration json file
     *
     * @throws GoogleException
     */
    public function __construct(string $config = '')
    {
        if (file_exists($config)) {
            $client = new \Google_Client();
            $client->setApplicationName('PHP ETL');
            $client->setScopes(['https://www.googleapis.com/auth/analytics.readonly']);
            $client->setConfig('retry', ['retries' => 5]);
            $client->setAuthConfig($config);

            $this->analyticsService = new \Google_Service_Analytics($client);
            $this->reportingService = new \Google_Service_AnalyticsReporting($client);
        }
        $this->reportRequest = new \Google_Service_AnalyticsReporting_ReportRequest();
    }

    public function options(array $options): Step
    {
        parent::options($options);
        $this->validate();

        if (!isset($this->delayFunction)) {
            // Delay 1 second to avoid 100 requests per 100 seconds quota exceeded.
            $this->delayFunction = function (): void {
                usleep($this->oneSecond);
            };
        }

        return $this;
    }

    /**
     * Extract data from the input.
     */
    public function extract(): \Generator
    {
        $this->reportRequestSetup($this->dimensions, $this->metrics, $this->startDate, $this->endDate);

        foreach ($this->getProfiles() as $profile) {
            [$propertyName, $profileSummary] = $profile;
            $this->delay();
            $summaryName = $profileSummary->getName();
            if (!$this->isWantedView($summaryName)) {
                continue;
            }

            $request = $this->reportRequest($profileSummary->getId());
            $response = $this->reportingService->reports->batchGet($request);

            /**
             * @var \Google_Service_AnalyticsReporting_Report $report
             */
            foreach ($response as $report) {
                $this->setHeaders($report);
                $rows = $report->getData()->getRows();
                foreach ($rows as $row) {
                    $rowData = $this->getRowData($row);
                    $rowData['property'] = $propertyName;
                    $rowData['summary'] = $summaryName;
                    yield new Row($rowData);
                }
            }
        }
    }

    /**
     * Delay requests to avoid exceeding Google quotas.
     *
     * Quota we are avoiding here is the "Requests per 100 seconds per user" setting in the API console. By default,
     * it is set to 100 requests per 100 seconds per user, and can be adjusted to a maximum value of 1,000.
     *
     * Additional quotas are:
     *
     *  - The total of requests to the API is restricted to a maximum of 50,000 requests per project per day
     *  - The number of requests to the API is restricted to a maximum of 10 requests per second per user.
     *
     * @see https://developers.google.com/analytics/devguides/config/mgmt/v3/limits-quotas
     */
    private function delay(): void
    {
        if ($this->clientReqCount >= 100) {
            call_user_func($this->delayFunction);
        }
        ++$this->clientReqCount;
    }

    /**
     * Returns the row data array, keyed by dimension and metrics headers.
     */
    private function getRowData(\Google_Service_AnalyticsReporting_ReportRow $row): array
    {
        return array_combine($this->dimensionHeaders, $row->getDimensions()) +
            array_combine($this->metricHeaders, $row->getMetrics()[0]->getValues());
    }

    /**
     * Gets an array of Web Properties that can be read by the provided client.
     *
     * @return \Google_Service_Analytics_ProfileSummary[]
     */
    private function getProfiles(): array
    {
        /** @var \Google_Service_Analytics_ProfileSummary[] $profiles */
        $profiles = [];
        $accountSummaries = $this->analyticsService->management_accountSummaries->listManagementAccountSummaries();
        foreach ($accountSummaries->getItems() as $accountSummary) {
            foreach ($accountSummary->getWebProperties() as $propertySummary) {
                $propertyName = $propertySummary->getName();
                if ($this->isWantedProperty($propertyName)) {
                    foreach ($propertySummary->getProfiles() as $profile) {
                        $profiles[] = [$propertyName, $profile];
                    }
                }
            }
        }

        return $profiles;
    }

    /**
     * Determines if reporting is desired for the specified property name.
     */
    private function isWantedProperty(string $name): bool
    {
        return 0 === count($this->properties) || in_array($name, $this->properties, true);
    }

    /**
     * Determines if reporting is desired for the specified profile name.
     */
    private function isWantedView(string $name): bool
    {
        return 0 === count($this->views) || in_array($name, $this->views, true);
    }

    /**
     * Creates GetReportsRequest object with current viewID and the shared request parameters set earlier.
     */
    private function reportRequest(string $viewId): \Google_Service_AnalyticsReporting_GetReportsRequest
    {
        $this->reportRequest->setViewId($viewId);

        $body = new \Google_Service_AnalyticsReporting_GetReportsRequest();
        $body->setReportRequests([$this->reportRequest]);

        return $body;
    }

    /**
     * Sets general parameters for report requests.
     *
     * Our requests only differ in having a different viewID, so we only need to set these once.
     */
    private function reportRequestSetup(array $dimensions, array $metrics, string $start, string $end): void
    {
        $this->reportRequest->setDateRanges(Request::dateRange($start, $end));
        $this->reportRequest->setDimensions(Request::dimensions($dimensions));
        $this->reportRequest->setMetrics(Request::metrics($metrics));
        $this->reportRequest->setPageSize(Request::REPORT_PAGE_SIZE);
        $this->reportRequest->setIncludeEmptyRows(true);
    }

    /**
     * Sets dimension and metrics headers for the current configuration.
     */
    private function setHeaders(\Google_Service_AnalyticsReporting_Report $report): void
    {
        $header = $report->getColumnHeader();
        $this->dimensionHeaders = $header->getDimensions();
        $headerEntries = $header->getMetricHeader()->getMetricHeaderEntries();
        $this->metricHeaders = array_map(
            function (\Google_Service_AnalyticsReporting_MetricHeaderEntry $headerEntry):string {
                return $headerEntry->getName();
            },
            $headerEntries
        );
    }

    /**
     * Validate the options passed in while setting up the ETL step.
     *
     * @throws \InvalidArgumentException
     */
    private function validate(): void
    {
        if (count($this->dimensions) < 1) {
            throw new \InvalidArgumentException('GoogleAnalytics Extractor requires at least one dimension');
        }
        if (count($this->dimensions) > 7) {
            throw new \InvalidArgumentException('GoogleAnalytics Extractor supports a maximum of 7 dimensions');
        }
        if (count($this->metrics) < 1) {
            throw new \InvalidArgumentException('GoogleAnalytics Extractor requires at least one metric');
        }
        if (count($this->metrics) > 10) {
            throw new \InvalidArgumentException('GoogleAnalytics Extractor supports a maximum of 10 metrics');
        }
        if ('' === $this->startDate) {
            throw new \InvalidArgumentException('GoogleAnalytics Extractor requires a start date');
        }
        if ('' === $this->endDate) {
            $this->endDate = date('Y-m-d', strtotime('-1 day'));
        }
    }
}
