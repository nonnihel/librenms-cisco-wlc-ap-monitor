<div style="font-family:Arial,Helvetica,sans-serif;max-width:760px;margin:auto;background:#f4f6f8;padding:20px;color:#263238;">
@if ($alert->state == 0)
  <div style="background:#2e7d32;color:white;padding:18px 22px;border-radius:8px 8px 0 0;">
    <div style="font-size:22px;font-weight:bold;">Access point restored</div>
  </div>
@else
  <div style="background:#c62828;color:white;padding:18px 22px;border-radius:8px 8px 0 0;">
    <div style="font-size:22px;font-weight:bold;">Access point offline</div>
  </div>
@endif
<div style="background:white;padding:22px;border:1px solid #d8dde2;border-top:0;border-radius:0 0 8px 8px;">
  <table style="width:100%;border-collapse:collapse;font-size:15px;">
    <tr><td style="padding:8px 0;width:180px;font-weight:bold;">Controller</td><td>{{ $alert->sysName ?? $alert->hostname }}</td></tr>
    <tr><td style="padding:8px 0;font-weight:bold;">IP / hostname</td><td>{{ $alert->hostname }}</td></tr>
    <tr><td style="padding:8px 0;font-weight:bold;">Severity</td><td>{{ ucfirst($alert->severity) }}</td></tr>
    <tr><td style="padding:8px 0;font-weight:bold;">Time</td><td>{{ $alert->timestamp }}</td></tr>
  </table>
  @foreach (($alert->faults ?? []) as $value)
    <div style="margin-top:14px;padding:15px;background:#f4f6f8;border-left:5px solid {{ $alert->state == 0 ? '#2e7d32' : '#c62828' }};">
      <div style="font-size:17px;font-weight:bold;">{{ $value['service_desc'] ?? 'Cisco WLC Access Point Monitor' }}</div>
      <div style="margin-top:8px;font-family:Consolas,monospace;font-size:14px;">{{ $value['service_message'] ?? $value['string'] ?? 'No additional details' }}</div>
    </div>
  @endforeach
</div>
</div>
