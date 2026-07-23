@php
    $up = (int) ($counts['up'] ?? 0);
    $down = (int) ($counts['down'] ?? 0);
    $ignored = (int) ($counts['ignored'] ?? 0);
    $retired = (int) ($counts['retired'] ?? 0);
@endphp

<div class="tw:p-2">
    <div class="tw:flex tw:flex-wrap tw:gap-2 tw:mb-3">
        <span class="label label-success">Up: {{ $up }}</span>
        <span class="label label-danger">Down: {{ $down }}</span>
        <span class="label label-default">Ignored: {{ $ignored }}</span>
        <span class="label label-info">Retired: {{ $retired }}</span>
    </div>

    @if ($downAps->isEmpty())
        @if ($showHealthy)
            <div class="alert alert-success tw:mb-0">
                <i class="fa fa-check-circle" aria-hidden="true"></i>
                All monitored Cisco access points are online.
            </div>
        @else
            <div class="text-muted">No access points are currently down.</div>
        @endif
    @else
        <div class="table-responsive">
            <table class="table table-condensed table-hover tw:mb-0">
                <thead>
                <tr>
                    <th>Access point</th>
                    <th>Controller</th>
                    <th>Down since</th>
                </tr>
                </thead>
                <tbody>
                @foreach ($downAps as $ap)
                    <tr>
                        <td>
                            <span class="label label-danger">DOWN</span>
                            <a href="{{ route('cisco-wlc-ap-monitor.show', $ap->id) }}">
                                {{ $ap->ap_name }}
                            </a>
                        </td>
                        <td>{{ $ap->sysName ?: $ap->hostname ?: ('Device ' . $ap->device_id) }}</td>
                        <td>{{ $ap->down_since ? \Illuminate\Support\Carbon::parse($ap->down_since)->diffForHumans() : 'Unknown' }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>

        @if ($down > $downAps->count())
            <div class="text-muted tw:mt-2">
                Showing {{ $downAps->count() }} of {{ $down }} down access points.
            </div>
        @endif
    @endif

    <div class="tw:mt-3">
        <a class="btn btn-primary btn-sm" href="{{ route('cisco-wlc-ap-monitor.index', array_filter(['device_id' => $deviceId])) }}">
            <i class="fa fa-wifi" aria-hidden="true"></i> View all access points
        </a>
    </div>
</div>
