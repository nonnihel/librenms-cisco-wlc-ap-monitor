@extends('widgets.settings.base')

@section('form')
    <div class="form-group">
        <label for="device-id-{{ $id }}" class="control-label">Cisco WLC</label>
        <select class="form-control" name="device_id" id="device-id-{{ $id }}">
            <option value="">All Cisco WLC devices</option>
            @foreach ($devices as $device)
                <option value="{{ $device->device_id }}" @selected((string) ($device_id ?? '') === (string) $device->device_id)>
                    {{ $device->sysName ?: $device->hostname }} ({{ $device->hostname }})
                </option>
            @endforeach
        </select>
    </div>

    <div class="form-group">
        <label for="limit-{{ $id }}" class="control-label">Maximum down APs to display</label>
        <input type="number" class="form-control" min="1" max="50" step="1" name="limit" id="limit-{{ $id }}" value="{{ $limit ?? 10 }}">
    </div>

    <div class="checkbox">
        <label>
            <input type="hidden" name="show_healthy" value="0">
            <input type="checkbox" name="show_healthy" value="1" @checked((bool) ($show_healthy ?? true))>
            Show a green healthy message when no APs are down
        </label>
    </div>
@endsection
