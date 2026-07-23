<?php

declare(strict_types=1);

namespace Averna\CiscoWlcApMonitor\Http\Controllers;

use App\Http\Controllers\Widgets\WidgetController;
use Averna\CiscoWlcApMonitor\Models\WlcAccessPoint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

final class CiscoWlcApMonitorWidgetController extends WidgetController
{
    protected string $name = 'cisco-wlc-ap-monitor';

    protected $defaults = [
        'title' => null,
        'device_id' => null,
        'limit' => 10,
        'show_healthy' => 1,
        'refresh' => 60,
    ];

    public function getTitle(): string
    {
        return 'Cisco WLC AP Monitor';
    }

    public function getView(Request $request): View|string
    {
        $settings = $this->getSettings();
        $deviceId = filter_var($settings['device_id'] ?? null, FILTER_VALIDATE_INT) ?: null;
        $limit = min(max((int) ($settings['limit'] ?? 10), 1), 50);
        $showHealthy = (bool) ($settings['show_healthy'] ?? true);

        $baseQuery = WlcAccessPoint::query();
        if ($deviceId !== null) {
            $baseQuery->where('cisco_wlc_ap_monitor.device_id', $deviceId);
        }

        $counts = (clone $baseQuery)
            ->selectRaw('state, COUNT(*) AS count')
            ->groupBy('state')
            ->pluck('count', 'state');

        $downAps = (clone $baseQuery)
            ->leftJoin('devices', 'devices.device_id', '=', 'cisco_wlc_ap_monitor.device_id')
            ->where('cisco_wlc_ap_monitor.state', 'down')
            ->select('cisco_wlc_ap_monitor.*', 'devices.hostname', 'devices.sysName')
            ->orderBy('cisco_wlc_ap_monitor.down_since')
            ->orderBy('cisco_wlc_ap_monitor.ap_name')
            ->limit($limit)
            ->get();

        return view('cisco-wlc-ap-monitor::dashboard-widget', [
            'counts' => $counts,
            'downAps' => $downAps,
            'deviceId' => $deviceId,
            'limit' => $limit,
            'showHealthy' => $showHealthy,
        ]);
    }

    public function getSettingsView(Request $request): View
    {
        $settings = $this->getSettings(true);
        $settings['devices'] = DB::table('devices')
            ->where('os', 'ciscowlc')
            ->where('disabled', 0)
            ->orderBy('hostname')
            ->get(['device_id', 'hostname', 'sysName']);

        return view('cisco-wlc-ap-monitor::dashboard-widget-settings', $settings);
    }
}
