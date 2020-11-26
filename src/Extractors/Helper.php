<?php

/**
 * @author      Karl DeBisschop <kdebisschop@gmail.com>
 * @copyright   Copyright (c) Karl DeBisschop
 * @license     MIT
 */

declare(strict_types=1);

namespace PhpEtl\GoogleAnalytics\Extractors;

/**
 * Provides some static methods that can be used to in Extractor code and in tests.
 */
class Helper
{
    public const REPORT_PAGE_SIZE = 1000;

    public static function metrics(array $metrics): array
    {
        // At least one metric required, max 10.
        $array = [];
        foreach ($metrics as $metric) {
            $reportingMetric = new \Google_Service_AnalyticsReporting_Metric();
            $reportingMetric->setExpression($metric['name']);
            $reportingMetric->setAlias(str_replace('ga:', '', $metric['name']));
            $reportingMetric->setFormattingType($metric['type']);
            $array[] = $reportingMetric;
        }

        return $array;
    }

    public static function dateRange(string $start, string $end): \Google_Service_AnalyticsReporting_DateRange
    {
        $dateRange = new \Google_Service_AnalyticsReporting_DateRange();
        $dateRange->setStartDate($start);
        $dateRange->setEndDate($end);

        return $dateRange;
    }

    public static function dimensions(array $dimensions): array
    {
        // Max 7 dimensions.
        $array = [];
        foreach ($dimensions as $dimension) {
            $reportDimension = new \Google_Service_AnalyticsReporting_Dimension();
            $reportDimension->setName($dimension);
            $array[] = $reportDimension;
        }

        return $array;
    }
}
