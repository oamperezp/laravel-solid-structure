<?php

namespace AmpTech\LaravelSolidStructure;

use Illuminate\Support\ServiceProvider;
use AmpTech\LaravelSolidStructure\Commands\MakeSolidCommand;

class SolidStructureServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                MakeSolidCommand::class,
            ]);
        }
    }
}