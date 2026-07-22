<?php

declare(strict_types=1);

namespace Averna\CiscoWlcApMonitor\Console;

use Averna\CiscoWlcApMonitor\Models\WlcAccessPoint;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Throwable;

final class PollCiscoWlcAccessPoints extends Command
{
    protected $signature = 'cisco-wlc-ap:poll
        {--device= : LibreNMS device_id or hostname; omitted means all ciscowlc devices}
        {--no-core-poll : Do not launch the LibreNMS OS poll before comparing inventory}
        {--timeout=240 : Maximum seconds allowed for each core OS poll}';

    protected $description = 'Track Cisco WLC AP up/down state without losing APs removed from the core access_points table.';

    public function handle(): int
    {
        $devices = DB::table('devices')
            ->where('os', 'ciscowlc')
            ->where('disabled', 0)
            ->when($this->option('device'), function ($query): void {
                $spec = (string) $this->option('device');
                $query->where(function ($inner) use ($spec): void {
                    if (ctype_digit($spec)) {
                        $inner->where('device_id', (int) $spec);
                    } else {
                        $inner->where('hostname', $spec);
                    }
                });
            })
            ->get(['device_id', 'hostname', 'sysName']);

        if ($devices->isEmpty()) {
            $this->warn('No enabled Cisco WLC devices matched.');
            return self::SUCCESS;
        }

        $failed = false;
        foreach ($devices as $device) {
            try {
                $this->pollDevice((int) $device->device_id, (string) $device->hostname);
            } catch (Throwable $e) {
                $failed = true;
                $this->error("{$device->hostname}: {$e->getMessage()}");
                report($e);
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    private function pollDevice(int $deviceId, string $hostname): void
    {
        $existingCount = WlcAccessPoint::query()->where('device_id', $deviceId)->count();

        if (! $this->option('no-core-poll')) {
            $process = new Process([
                PHP_BINARY,
                base_path('lnms'),
                'device:poll',
                (string) $deviceId,
                '--modules',
                'os',
                '--quiet',
                '--no-interaction',
            ], base_path());
            $process->setTimeout((int) $this->option('timeout'));
            $process->run();

            if (! $process->isSuccessful()) {
                throw new \RuntimeException('LibreNMS OS poll failed: ' . trim($process->getErrorOutput() ?: $process->getOutput()));
            }
        }

        $after = $this->coreInventory($deviceId);
        $now = Carbon::now();

        if ($existingCount === 0) {
            foreach ($after as $name => $row) {
                WlcAccessPoint::query()->create([
                    'device_id' => $deviceId,
                    'ap_name' => $name,
                    'radio_mac' => $row->mac_addr,
                    'state' => 'up',
                    'first_seen_at' => $now,
                    'last_seen_at' => $now,
                ]);
            }
            $this->info("{$hostname}: seeded " . count($after) . ' APs.');
            return;
        }

        foreach ($after as $name => $row) {
            $ap = WlcAccessPoint::query()->firstOrNew([
                'device_id' => $deviceId,
                'ap_name' => $name,
            ]);

            $wasDown = $ap->exists && $ap->state === 'down';
            $isRetired = $ap->exists && $ap->state === 'retired';
            $isIgnored = $ap->exists && $ap->state === 'ignored';

            $ap->radio_mac = $row->mac_addr;
            $ap->first_seen_at ??= $now;
            $ap->last_seen_at = $now;

            if (! $isRetired && ! $isIgnored) {
                $ap->state = 'up';
                $ap->down_since = null;
            }

            if ($wasDown) {
                $ap->transition_count = (int) $ap->transition_count + 1;
                $this->line("{$hostname}: RECOVERED {$name}");
            }

            $ap->save();
        }

        WlcAccessPoint::query()
            ->where('device_id', $deviceId)
            ->whereNotIn('ap_name', array_keys($after))
            ->whereNotIn('state', ['ignored', 'retired'])
            ->get()
            ->each(function (WlcAccessPoint $ap) use ($now, $hostname): void {
                if ($ap->state !== 'down') {
                    $ap->state = 'down';
                    $ap->down_since = $now;
                    $ap->transition_count = (int) $ap->transition_count + 1;
                    $ap->save();
                    $this->warn("{$hostname}: DOWN {$ap->ap_name}");
                }
            });

        $this->info("{$hostname}: " . count($after) . ' APs currently online.');
    }

    /**
     * Return one row per AP name. The core table stores one row per radio.
     *
     * @return array<string, object>
     */
    private function coreInventory(int $deviceId): array
    {
        return DB::table('access_points')
            ->where('device_id', $deviceId)
            ->selectRaw('name, MIN(mac_addr) AS mac_addr')
            ->groupBy('name')
            ->orderBy('name')
            ->get()
            ->keyBy('name')
            ->all();
    }
}
