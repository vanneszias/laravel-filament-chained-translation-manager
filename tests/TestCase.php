<?php

namespace Statikbe\FilamentTranslationManager\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Statikbe\FilamentTranslationManager\FilamentTranslationManagerServiceProvider;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            FilamentTranslationManagerServiceProvider::class,
        ];
    }
}
