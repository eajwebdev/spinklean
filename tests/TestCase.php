<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    public function createApplication()
    {
        $app = parent::createApplication();
        $app['env'] = 'testing';
        putenv('APP_ENV=testing');

        return $app;
    }

    protected function migrateFreshUsing()
    {
        return [
            '--drop-views' => true,
            '--force' => true,
        ];
    }
}
