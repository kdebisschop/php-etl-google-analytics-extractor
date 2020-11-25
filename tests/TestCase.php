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
 * Class TestCase
 *
 * @package Tests
 */
abstract class TestCase extends BaseTestCase
{

    /**
     * @param $step
     * @param $data
     */
    protected function execute($step, $data)
    {
        if ($step instanceof Transformer) {
            $method = 'transform';
        }

        if ($step instanceof Loader) {
            $method = 'load';
        }

        $step->initialize();

        foreach ($data as $row) {
            $step->$method($row);
        }

        $step->finalize();
    }
}
