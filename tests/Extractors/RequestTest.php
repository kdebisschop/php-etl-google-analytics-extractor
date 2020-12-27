<?php

/**
 * @author      Karl DeBisschop <kdebisschop@gmail.com>
 * @copyright   Copyright (c) Karl DeBisschop
 * @license     MIT
 */

declare(strict_types=1);

namespace PhpEtl\GoogleAnalytics\Tests\Extractors;

use PhpEtl\GoogleAnalytics\Extractors\Request;
use PhpEtl\GoogleAnalytics\Tests\TestCase;

/**
 * Tests Request.
 *
 * @coversDefaultClass \PhpEtl\GoogleAnalytics\Extractors\Request
 */
class RequestTest extends TestCase
{
    /**
     * @test
     * @covers ::metrics
     */
    public function testMetrics(): void
    {
        /**
         * @var \Google_Service_AnalyticsReporting_Metric[]
         */
        $expected = [
            RequestTest::metric('ga:pageviews', 'INTEGER'),
            RequestTest::metric('ga:sessions', 'INTEGER'),
        ];
        $actual = Request::metrics([
            ['name' => $expected[0]->getExpression(), 'type' => $expected[0]->getFormattingType()],
            ['name' => $expected[1]->getExpression(), 'type' => $expected[1]->getFormattingType()],
        ]);
        static::assertEquals($expected, $actual);
    }

    /**
     * @test
     * @covers ::dateRange()
     */
    public function testDateRange(): void
    {
        $expected = RequestTest::dateRange('2020-12-01', '2020-12-31');
        $actual = Request::dateRange($expected->getStartDate(), $expected->getEndDate());
        static::assertEquals($expected, $actual);
    }

    /**
     * @test
     * @covers ::dimensions
     */
    public function testDimensions(): void
    {
        /**
         * @var \Google_Service_AnalyticsReporting_Dimension[]
         */
        $expected = [
            RequestTest::dimension('ga:date'),
            RequestTest::dimension('ga:site'),
        ];
        $actual = Request::dimensions([
            $expected[0]->getName(),
            $expected[1]->getName(),
        ]);
        static::assertEquals($expected, $actual);
    }

    private static function metric(string $name, string $type): \Google_Service_AnalyticsReporting_Metric
    {
        $metric = new \Google_Service_AnalyticsReporting_Metric();
        $metric->setExpression($name);
        $metric->setAlias(str_replace('ga:', '', $name));
        $metric->setFormattingType($type);

        return $metric;
    }

    public static function dateRange(string $start, string $end): \Google_Service_AnalyticsReporting_DateRange
    {
        $dateRange = new \Google_Service_AnalyticsReporting_DateRange();
        $dateRange->setStartDate($start);
        $dateRange->setEndDate($end);

        return $dateRange;
    }

    private static function dimension(string $name): \Google_Service_AnalyticsReporting_Dimension
    {
        $dimension = new \Google_Service_AnalyticsReporting_Dimension();
        $dimension->setName($name);

        return $dimension;
    }
}
