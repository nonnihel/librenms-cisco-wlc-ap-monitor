@extends('layouts.librenmsv1')

@section('title', 'Cisco WLC AP Monitor')

@section('content')
@php
    $sortLink = function (string $column) use ($sort, $direction) {
        $next = $sort === $column && $direction === 'asc' ? 'desc' : 'asc';
        return request()->fullUrlWithQuery(['sort' => $column, 'direction' => $next, 'page' => null]);
    };
    $sortMark = fn (string $column) => $sort === $column ? ($direction === 'asc' ? ' ▲' : ' ▼') : '';
@endphp
<div class="container-fluid">
    <div class="row">
        <div class="col-md-12">
            <div class="panel panel-default">
                <div class="panel-heading"><strong>Cisco WLC AP Monitor</strong></div>
                <div class="panel-body">
                    @if (session('status'))
                        <div class="alert alert-success">{{ session('status') }}</div>
                    @endif

                    <div class="row" style="margin-bottom: 15px;">
                        @foreach (['up' => 'success', 'down' => 'danger', 'ignored' => 'warning', 'retired' => 'default'] as $key => $class)
                            <div class="col-sm-3">
                                <div class="panel panel-{{ $class }}">
                                    <div class="panel-heading">{{ ucfirst($key) }}</div>
                                    <div class="panel-body" style="font-size: 24px;">{{ $counts[$key] ?? 0 }}</div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <form method="get" class="form-inline" style="margin-bottom: 15px;">
                        <div class="form-group">
                            <select name="state" class="form-control">
                                @foreach (['active' => 'Active', 'up' => 'Up', 'down' => 'Down', 'ignored' => 'Ignored', 'retired' => 'Retired', 'all' => 'All'] as $value => $label)
                                    <option value="{{ $value }}" @selected($state === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group">
                            <select name="device_id" class="form-control">
                                <option value="">All WLCs</option>
                                @foreach ($devices as $device)
                                    <option value="{{ $device->device_id }}" @selected((int) $deviceId === (int) $device->device_id)>
                                        {{ $device->sysName ?: $device->hostname }} ({{ $device->hostname }})
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group">
                            <input name="search" class="form-control" value="{{ $search }}" placeholder="AP name, MAC, channel or WLC">
                        </div>
                        <input type="hidden" name="sort" value="{{ $sort }}">
                        <input type="hidden" name="direction" value="{{ $direction }}">
                        <button class="btn btn-primary" type="submit">Filter</button>
                        <a class="btn btn-default" href="{{ route('cisco-wlc-ap-monitor.index') }}">Reset</a>
                    </form>

                    <div class="table-responsive">
                        <table class="table table-striped table-condensed table-hover">
                            <thead>
                                <tr>
                                    <th><a href="{{ $sortLink('state') }}">Status{!! $sortMark('state') !!}</a></th>
                                    <th><a href="{{ $sortLink('ap_name') }}">Access point{!! $sortMark('ap_name') !!}</a></th>
                                    <th><a href="{{ $sortLink('controller') }}">Controller{!! $sortMark('controller') !!}</a></th>
                                    <th><a href="{{ $sortLink('clients') }}">Clients{!! $sortMark('clients') !!}</a></th>
                                    <th><a href="{{ $sortLink('radios') }}">Radios{!! $sortMark('radios') !!}</a></th>
                                    <th>Channels</th>
                                    <th><a href="{{ $sortLink('utilization') }}">Max util.{!! $sortMark('utilization') !!}</a></th>
                                    <th><a href="{{ $sortLink('radio_mac') }}">Radio MAC{!! $sortMark('radio_mac') !!}</a></th>
                                    <th><a href="{{ $sortLink('last_seen') }}">Last seen{!! $sortMark('last_seen') !!}</a></th>
                                    <th><a href="{{ $sortLink('down_since') }}">Down since{!! $sortMark('down_since') !!}</a></th>
                                    <th>Reason</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($aps as $ap)
                                    @php
                                        $labels = ['up' => 'success', 'down' => 'danger', 'ignored' => 'warning', 'retired' => 'default'];
                                    @endphp
                                    <tr>
                                        <td><span class="label label-{{ $labels[$ap->state] ?? 'default' }}">{{ strtoupper($ap->state) }}</span></td>
                                        <td><strong>{{ $ap->ap_name }}</strong></td>
                                        <td>{{ $ap->sysName ?: $ap->hostname }}<br><small>{{ $ap->hostname }}</small></td>
                                        <td>{{ $ap->client_count ?? '—' }}</td>
                                        <td>{{ $ap->radio_count ?? '—' }}</td>
                                        <td>{{ $ap->channels ?: '—' }}</td>
                                        <td>{{ $ap->max_utilization !== null ? $ap->max_utilization . '%' : '—' }}</td>
                                        <td><code>{{ $ap->radio_mac ?: '—' }}</code></td>
                                        <td>{{ optional($ap->last_seen_at)->format('Y-m-d H:i:s') ?: '—' }}</td>
                                        <td>{{ optional($ap->down_since)->format('Y-m-d H:i:s') ?: '—' }}</td>
                                        <td>{{ $ap->reason ?: '—' }}</td>
                                        <td style="min-width: 310px;">
                                            <form method="post" action="{{ route('cisco-wlc-ap-monitor.action', $ap->id) }}" class="form-inline">
                                                @csrf
                                                <input type="text" name="reason" class="form-control input-sm" maxlength="255" placeholder="Reason" style="width: 110px;">
                                                @if (!in_array($ap->state, ['ignored', 'retired'], true))
                                                    <button name="action" value="ignore" class="btn btn-warning btn-xs">Ignore</button>
                                                    <button name="action" value="retire" class="btn btn-default btn-xs">Retire</button>
                                                @else
                                                    <button name="action" value="restore" class="btn btn-success btn-xs">Restore</button>
                                                @endif
                                                <button name="action" value="delete" class="btn btn-danger btn-xs" onclick="return confirm('Delete this AP and its history? It will be rediscovered if currently online.');">Delete</button>
                                            </form>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="12" class="text-muted">No access points match this filter.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    {{ $aps->links() }}

                    <div class="alert alert-info" style="margin-top: 15px;">
                        Click a column heading to sort. Client count, radios, channels and utilization come from the current LibreNMS wireless inventory and the last known values remain visible while an AP is down.
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
