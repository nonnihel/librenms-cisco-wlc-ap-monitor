@extends('layouts.librenmsv1')

@section('title', $ap->ap_name . ' - Cisco WLC AP Monitor')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-md-12">
            <div class="panel panel-default">
                <div class="panel-heading">
                    <strong>{{ $ap->ap_name }}</strong>
                    <a href="{{ route('cisco-wlc-ap-monitor.index') }}" class="btn btn-default btn-xs pull-right">
                        <i class="fa fa-arrow-left" aria-hidden="true"></i> All access points
                    </a>
                </div>
                <div class="panel-body">
                    @php
                        $labels = ['up' => 'success', 'down' => 'danger', 'ignored' => 'warning', 'retired' => 'default'];
                    @endphp

                    <div style="margin-bottom: 15px;">
                        <span class="label label-{{ $labels[$ap->state] ?? 'default' }}">{{ strtoupper($ap->state) }}</span>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-striped table-condensed">
                            <tbody>
                            <tr><th style="width: 240px;">Access point</th><td>{{ $ap->ap_name }}</td></tr>
                            <tr><th>Local IP address</th><td>{{ $ap->local_ip ?: '—' }}</td></tr>
                            <tr><th>Controller</th><td>{{ $ap->sysName ?: $ap->hostname ?: ('Device ' . $ap->device_id) }}</td></tr>
                            <tr><th>Radio MAC</th><td>{{ $ap->radio_mac ?: '—' }}</td></tr>
                            <tr><th>Location</th><td>{{ $ap->location ?: '—' }}</td></tr>
                            <tr><th>Connected clients</th><td>{{ $ap->client_count ?? '—' }}</td></tr>
                            <tr><th>Radios</th><td>{{ $ap->radio_count ?? '—' }}</td></tr>
                            <tr><th>Channels</th><td>{{ $ap->channels ?: '—' }}</td></tr>
                            <tr><th>Maximum utilization</th><td>{{ $ap->max_utilization !== null ? $ap->max_utilization . '%' : '—' }}</td></tr>
                            <tr><th>First seen</th><td>{{ optional($ap->first_seen_at)->format('Y-m-d H:i:s') ?: '—' }}</td></tr>
                            <tr><th>Last seen</th><td>{{ optional($ap->last_seen_at)->format('Y-m-d H:i:s') ?: '—' }}</td></tr>
                            <tr><th>Down since</th><td>{{ optional($ap->down_since)->format('Y-m-d H:i:s') ?: '—' }}</td></tr>
                            <tr><th>State transitions</th><td>{{ $ap->transition_count ?? 0 }}</td></tr>
                            <tr><th>Reason</th><td>{{ $ap->reason ?: '—' }}</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection