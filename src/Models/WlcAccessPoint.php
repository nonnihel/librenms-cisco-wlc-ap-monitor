<?php

declare(strict_types=1);

namespace Averna\CiscoWlcApMonitor\Models;

use Illuminate\Database\Eloquent\Model;

final class WlcAccessPoint extends Model
{
    protected $table = 'cisco_wlc_ap_monitor';

    protected $guarded = [];

    protected $casts = [
        'first_seen_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'down_since' => 'datetime',
        'retired_at' => 'datetime',
        'ignored_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
