<?php

namespace Oasis\Providers;

use Illuminate\Support\ServiceProvider;

class ExamineConfigServiceProvider extends ServiceProvider
{
    /**
     * Indicates if loading of the provider is deferred.
     *
     * @var bool
     */
    protected $defer = false;

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->commands('\Oasis\Console\Commands\RebaseDatabaseCommand');
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return [];
    }
}
