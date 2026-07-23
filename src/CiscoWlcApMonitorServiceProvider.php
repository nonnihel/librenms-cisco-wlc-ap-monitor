<?php

declare(strict_types=1);

namespace Averna\CiscoWlcApMonitor;

use Averna\CiscoWlcApMonitor\Console\PollCiscoWlcAccessPoints;
use Averna\CiscoWlcApMonitor\Hooks\MenuEntry;
use Averna\CiscoWlcApMonitor\Http\Controllers\CiscoWlcApMonitorWidgetController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use LibreNMS\Interfaces\Plugins\Hooks\MenuEntryHook;
use LibreNMS\Interfaces\Plugins\PluginManagerInterface;

final class CiscoWlcApMonitorServiceProvider extends ServiceProvider
{
    public function boot(PluginManagerInterface $pluginManager): void
    {
        $pluginName = 'cisco-wlc-ap-monitor';

        // Register a LibreNMS plugin menu entry. LibreNMS renders plugin hooks
        // in its Plugins menu area; this does not modify LibreNMS core files.
        $pluginManager->publishHook($pluginName, MenuEntryHook::class, MenuEntry::class);

        $this->loadViewsFrom(__DIR__ . '/../resources/views', $pluginName);
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        Route::middleware(['web', 'auth'])
            ->prefix('cisco-wlc-ap-monitor')
            ->name('cisco-wlc-ap-monitor.')
            ->group(function (): void {
                require __DIR__ . '/../routes.php';
            });

        // LibreNMS discovers dashboard widgets from routes registered below ajax/dash.
        Route::middleware(['web', 'auth'])
            ->prefix('ajax/dash')
            ->name('cisco-wlc-ap-monitor.dashboard.')
            ->group(function (): void {
                Route::match(['get', 'post'], 'cisco-wlc-ap-monitor', CiscoWlcApMonitorWidgetController::class)
                    ->name('widget');
            });

        if ($this->app->runningInConsole()) {
            $this->commands([PollCiscoWlcAccessPoints::class]);
        }
    }
}
