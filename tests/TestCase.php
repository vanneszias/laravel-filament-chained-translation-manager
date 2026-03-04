<?php

namespace Statikbe\FilamentTranslationManager\Tests;

use Livewire\LivewireServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use Statikbe\FilamentTranslationManager\FilamentTranslationManagerServiceProvider;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            LivewireServiceProvider::class,
            FilamentTranslationManagerServiceProvider::class,
        ];
    }
}

