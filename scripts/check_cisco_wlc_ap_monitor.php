#!/usr/bin/env php
<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

$base = getenv('LIBRENMS_BASE') ?: '/opt/librenms';
require $base . '/vendor/autoload.php';
$app = require $base . '/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$options = getopt('H:', ['device-id:', 'warning::']);
$deviceId = isset($options['device-id']) ? (int) $options['device-id'] : 0;
if ($deviceId < 1) {
    fwrite(STDERR, "UNKNOWN - --device-id is required\n");
    exit(3);
}

$rows = DB::table('cisco_wlc_ap_monitor')
    ->where('device_id', $deviceId)
    ->where('state', 'down')
    ->orderBy('ap_name')
    ->get(['ap_name', 'down_since']);

$host = DB::table('devices')->where('device_id', $deviceId)->value('hostname') ?: "device_id {$deviceId}";
$count = $rows->count();

if ($count === 0) {
    echo "OK - all monitored APs are online on {$host} | down=0\n";
    exit(0);
}

$names = $rows->pluck('ap_name')->implode(', ');
echo "CRITICAL - {$count} AP(s) down on {$host}: {$names} | down={$count}\n";
exit(2);
