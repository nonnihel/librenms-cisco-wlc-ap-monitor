<?php

declare(strict_types=1);

namespace Averna\CiscoWlcApMonitor\Http\Controllers;

use Averna\CiscoWlcApMonitor\Models\WlcAccessPoint;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

final class AccessPointController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()?->can('admin'), 403);

        $state = (string) $request->query('state', 'active');
        $deviceId = $request->integer('device_id') ?: null;
        $search = trim((string) $request->query('search', ''));
        $sort = (string) $request->query('sort', 'state');
        $direction = strtolower((string) $request->query('direction', 'asc')) === 'desc' ? 'desc' : 'asc';

        $sortable = [
            'state' => 'cisco_wlc_ap_monitor.state',
            'ap_name' => 'cisco_wlc_ap_monitor.ap_name',
            'controller' => 'devices.hostname',
            'radio_mac' => 'cisco_wlc_ap_monitor.radio_mac',
            'clients' => 'cisco_wlc_ap_monitor.client_count',
            'radios' => 'cisco_wlc_ap_monitor.radio_count',
            'utilization' => 'cisco_wlc_ap_monitor.max_utilization',
            'last_seen' => 'cisco_wlc_ap_monitor.last_seen_at',
            'down_since' => 'cisco_wlc_ap_monitor.down_since',
        ];
        if (! array_key_exists($sort, $sortable)) {
            $sort = 'state';
        }

        $query = WlcAccessPoint::query()
            ->leftJoin('devices', 'devices.device_id', '=', 'cisco_wlc_ap_monitor.device_id')
            ->select('cisco_wlc_ap_monitor.*', 'devices.hostname', 'devices.sysName');

        if ($state === 'active') {
            $query->whereIn('state', ['up', 'down']);
        } elseif (in_array($state, ['up', 'down', 'ignored', 'retired'], true)) {
            $query->where('state', $state);
        }

        if ($deviceId !== null) {
            $query->where('cisco_wlc_ap_monitor.device_id', $deviceId);
        }

        if ($search !== '') {
            $query->where(function ($inner) use ($search): void {
                $inner->where('ap_name', 'like', "%{$search}%")
                    ->orWhere('radio_mac', 'like', "%{$search}%")
                    ->orWhere('channels', 'like', "%{$search}%")
                    ->orWhere('devices.hostname', 'like', "%{$search}%")
                    ->orWhere('devices.sysName', 'like', "%{$search}%");
            });
        }

        $aps = $query
            ->orderBy($sortable[$sort], $direction)
            ->orderBy('cisco_wlc_ap_monitor.ap_name')
            ->paginate(100)
            ->withQueryString();

        $devices = DB::table('devices')
            ->where('os', 'ciscowlc')
            ->orderBy('hostname')
            ->get(['device_id', 'hostname', 'sysName']);

        $counts = WlcAccessPoint::query()
            ->selectRaw('state, COUNT(*) AS count')
            ->groupBy('state')
            ->pluck('count', 'state');

        return view('cisco-wlc-ap-monitor::index', compact(
            'aps', 'devices', 'counts', 'state', 'deviceId', 'search', 'sort', 'direction'
        ));
    }

    public function show(Request $request, int $id): View
    {
        abort_unless($request->user()?->can('admin'), 403);

        $ap = WlcAccessPoint::query()
            ->leftJoin('devices', 'devices.device_id', '=', 'cisco_wlc_ap_monitor.device_id')
            ->select('cisco_wlc_ap_monitor.*', 'devices.hostname', 'devices.sysName')
            ->findOrFail($id);

        return view('cisco-wlc-ap-monitor::show', compact('ap'));
    }

    public function widget(Request $request): View
    {
        abort_unless($request->user() !== null, 403);

        $deviceId = $request->integer('device_id') ?: null;
        $limit = min(max($request->integer('limit', 10), 1), 50);
        $refresh = min(max($request->integer('refresh', 60), 15), 3600);

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

        return view('cisco-wlc-ap-monitor::widget', compact('counts', 'downAps', 'deviceId', 'limit', 'refresh'));
    }

    public function action(Request $request, int $id): RedirectResponse
    {
        abort_unless($request->user()?->can('admin'), 403);

        $validated = $request->validate([
            'action' => ['required', 'in:ignore,retire,restore,delete'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $ap = WlcAccessPoint::query()->findOrFail($id);
        $reason = trim((string) ($validated['reason'] ?? '')) ?: null;

        switch ($validated['action']) {
            case 'ignore':
                $ap->state = 'ignored';
                $ap->ignored_at = now();
                $ap->retired_at = null;
                $ap->reason = $reason;
                $ap->save();
                break;
            case 'retire':
                $ap->state = 'retired';
                $ap->retired_at = now();
                $ap->ignored_at = null;
                $ap->reason = $reason;
                $ap->save();
                break;
            case 'restore':
                $isCurrentlyPresent = DB::table('access_points')
                    ->where('device_id', $ap->device_id)
                    ->where(function ($query) use ($ap): void {
                        $query->where('name', $ap->ap_name);
                        if ($ap->radio_mac) {
                            $query->orWhere('mac_addr', $ap->radio_mac);
                        }
                    })
                    ->exists();
                $ap->state = $isCurrentlyPresent ? 'up' : 'down';
                $ap->down_since = $isCurrentlyPresent ? null : ($ap->down_since ?? now());
                $ap->ignored_at = null;
                $ap->retired_at = null;
                $ap->reason = null;
                $ap->save();
                break;
            case 'delete':
                $ap->delete();
                break;
        }

        return back()->with('status', "Action '{$validated['action']}' applied to {$ap->ap_name}.");
    }
}
