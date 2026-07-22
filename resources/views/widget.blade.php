<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="refresh" content="{{ $refresh }}">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Cisco WLC AP Status</title>
    <style>
        :root { color-scheme: dark; }
        * { box-sizing: border-box; }
        body { margin: 0; padding: 10px; background: #2b3036; color: #e7eaed; font-family: Arial, Helvetica, sans-serif; }
        .top { display: grid; grid-template-columns: repeat(3, minmax(0,1fr)); gap: 8px; margin-bottom: 10px; }
        .card { border-radius: 4px; overflow: hidden; background: #343a40; border: 1px solid #454b51; }
        .card .label { padding: 7px 10px; font-size: 13px; font-weight: 700; }
        .card .value { padding: 8px 10px; font-size: 24px; }
        .up .label { background: #66b95a; color: #fff; }
        .down .label { background: #d96359; color: #fff; }
        .ignored .label { background: #e79a20; color: #fff; }
        .header { display:flex; align-items:center; justify-content:space-between; gap:8px; margin: 0 0 7px; }
        .title { font-size: 15px; font-weight: 700; }
        a { color:#9fc5ff; text-decoration:none; }
        a:hover { text-decoration:underline; }
        table { width:100%; border-collapse:collapse; font-size:13px; background:#30353b; border:1px solid #454b51; }
        th, td { text-align:left; padding:7px 8px; border-bottom:1px solid #454b51; vertical-align:top; }
        th { color:#cfd5db; background:#292e33; }
        .badge { display:inline-block; background:#d9534f; color:#fff; border-radius:3px; padding:2px 6px; font-size:11px; font-weight:700; }
        .empty { padding:14px; border:1px solid #454b51; background:#30353b; color:#aeb6bd; }
        .muted { color:#aeb6bd; font-size:12px; }
        @media (max-width: 700px) { .top { grid-template-columns:1fr; } th:nth-child(3), td:nth-child(3) { display:none; } }
    </style>
</head>
<body>
    <div class="top">
        <div class="card up"><div class="label">Up</div><div class="value">{{ $counts['up'] ?? 0 }}</div></div>
        <div class="card down"><div class="label">Down</div><div class="value">{{ $counts['down'] ?? 0 }}</div></div>
        <div class="card ignored"><div class="label">Ignored</div><div class="value">{{ $counts['ignored'] ?? 0 }}</div></div>
    </div>

    <div class="header">
        <div class="title">Cisco WLC AP Status</div>
        <a href="{{ route('cisco-wlc-ap-monitor.index', array_filter(['state' => 'active', 'device_id' => $deviceId])) }}" target="_top">Open full monitor</a>
    </div>

    @if ($downAps->isEmpty())
        <div class="empty">All monitored access points are online.</div>
    @else
        <table>
            <thead><tr><th>Status</th><th>Access point</th><th>Controller</th><th>Last seen</th><th>Down since</th></tr></thead>
            <tbody>
            @foreach ($downAps as $ap)
                <tr>
                    <td><span class="badge">DOWN</span></td>
                    <td><strong>{{ $ap->ap_name }}</strong><div class="muted">{{ $ap->radio_mac ?: '—' }}</div></td>
                    <td>{{ $ap->sysName ?: $ap->hostname }}<div class="muted">{{ $ap->hostname }}</div></td>
                    <td>{{ optional($ap->last_seen_at)->format('Y-m-d H:i:s') ?: '—' }}</td>
                    <td>{{ optional($ap->down_since)->format('Y-m-d H:i:s') ?: '—' }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @endif
</body>
</html>
