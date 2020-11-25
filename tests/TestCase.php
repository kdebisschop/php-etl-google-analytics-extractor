<?php

/**
 * @author      Wizacha DevTeam <dev@wizacha.com>
 * @copyright   Copyright (c) Wizacha
 * @copyright   Copyright (c) Leonardo Marquine
 * @license     MIT
 */

declare(strict_types=1);

namespace PhpEtl\GoogleAnalytics\Tests;

use PHPUnit\Framework\TestCase as BaseTestCase;
use Wizaplace\Etl\Loaders\Loader;
use Wizaplace\Etl\Transformers\Transformer;

/**
 * Base class for PHP-ETL tests.
 */
abstract class TestCase extends BaseTestCase
{
    /**
     * @param Transformer|Loader $step
     * @param array              $data
     */
    protected function execute($step, $data): void
    {
        $step->initialize();

        if ($step instanceof Transformer) {
            foreach ($data as $row) {
                $step->transform($row);
            }
        }

        if ($step instanceof Loader) {
            foreach ($data as $row) {
                $step->load($row);
            }
        }

        $step->finalize();
    }
}
