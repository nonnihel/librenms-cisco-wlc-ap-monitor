<?php

declare(strict_types=1);

namespace Averna\CiscoWlcApMonitor;

use Averna\CiscoWlcApMonitor\Console\PollCiscoWlcAccessPoints;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class CiscoWlcApMonitorServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'cisco-wlc-ap-monitor');
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        Route::middleware(['web', 'auth'])
            ->prefix('cisco-wlc-ap-monitor')
            ->name('cisco-wlc-ap-monitor.')
            ->group(function (): void {
                require __DIR__ . '/../routes.php';
            });

        if ($this->app->runningInConsole()) {
            $this->commands([PollCiscoWlcAccessPoints::class]);
        }
    }
}
