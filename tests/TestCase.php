<?php

declare(strict_types=1);

namespace Beziworld\WFirma\Tests;

use Beziworld\WFirma\WFirmaServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [WFirmaServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('wfirma.access_key', 'access');
        $app['config']->set('wfirma.secret_key', 'secret');
        $app['config']->set('wfirma.app_key', 'app');
        $app['config']->set('wfirma.company_id', '777');
    }
}
