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

    protected $description = 'Track Cisco WLC AP state and retain inventory details after APs disappear from the core table.';

    public function handle(): int
    {
        $devices = DB::table('devices')
            ->where('os', 'ciscowlc')
            ->where('disabled', 0)
            ->when($this->option('device'), function ($query): void {
                $spec = (string) $this->option('device');
                $query->where(function ($inner) use ($spec): void {
                    ctype_digit($spec)
                        ? $inner->where('device_id', (int) $spec)
                        : $inner->where('hostname', $spec);
                });
            })
            ->get();

        if ($devices->isEmpty()) {
            $this->warn('No enabled Cisco WLC devices matched.');
            return self::SUCCESS;
        }

        $failed = false;
        foreach ($devices as $device) {
            try {
                $this->pollDevice((array) $device);
            } catch (Throwable $e) {
                $failed = true;
                $hostname = (string) ($device->hostname ?? $device->device_id ?? 'WLC');
                $this->error("{$hostname}: {$e->getMessage()}");
                report($e);
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @param array<string, mixed> $device
     */
    private function pollDevice(array $device): void
    {
        $deviceId = (int) $device['device_id'];
        $hostname = (string) $device['hostname'];

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

        $inventory = $this->coreInventory($deviceId);
        $localIps = $this->localIpInventory($device);
        $now = Carbon::now();
        $seenIds = [];

        foreach ($inventory as $name => $row) {
            $mac = $this->normalizeMac((string) ($row->mac_addr ?? ''));

            // Prefer the stable radio MAC so an AP rename updates the existing row.
            $ap = $mac !== ''
                ? WlcAccessPoint::query()->where('device_id', $deviceId)->where('radio_mac', $mac)->first()
                : null;

            $ap ??= WlcAccessPoint::query()->firstOrNew([
                'device_id' => $deviceId,
                'ap_name' => $name,
            ]);

            $wasDown = $ap->exists && $ap->state === 'down';
            $isRetired = $ap->exists && $ap->state === 'retired';
            $isIgnored = $ap->exists && $ap->state === 'ignored';
            $oldName = $ap->ap_name;

            $ap->ap_name = $name;
            $ap->radio_mac = $mac !== '' ? $mac : null;
            $ap->local_ip = $localIps[$name] ?? $ap->local_ip;
            $ap->client_count = isset($row->client_count) ? (int) $row->client_count : null;
            $ap->radio_count = isset($row->radio_count) ? (int) $row->radio_count : null;
            $ap->channels = $row->channels ?: null;
            $ap->max_utilization = isset($row->max_utilization) ? (int) $row->max_utilization : null;
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

            if ($ap->exists && $oldName && $oldName !== $name) {
                $this->line("{$hostname}: RENAMED {$oldName} -> {$name}");
            }

            $ap->save();
            $seenIds[] = $ap->getKey();
        }

        WlcAccessPoint::query()
            ->where('device_id', $deviceId)
            ->when($seenIds !== [], fn ($query) => $query->whereNotIn('id', $seenIds))
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

        $ipCount = count(array_filter($localIps));
        $this->info("{$hostname}: " . count($inventory) . " APs currently online; {$ipCount} local IP addresses collected.");
    }

    /**
     * Return one aggregate row per AP name. LibreNMS stores one row per radio.
     *
     * @return array<string, object>
     */
    private function coreInventory(int $deviceId): array
    {
        return DB::table('access_points')
            ->where('device_id', $deviceId)
            ->selectRaw("name,
                MIN(mac_addr) AS mac_addr,
                COUNT(*) AS radio_count,
                SUM(COALESCE(numasoclients, 0)) AS client_count,
                GROUP_CONCAT(DISTINCT NULLIF(channel, 0) ORDER BY channel SEPARATOR ', ') AS channels,
                MAX(COALESCE(radioutil, 0)) AS max_utilization")
            ->groupBy('name')
            ->orderBy('name')
            ->get()
            ->keyBy('name')
            ->all();
    }

    /**
     * Collect AP names and AP-local addresses from the WLC using the same SNMP
     * credentials and transport settings already configured on the LibreNMS device.
     * Both columns share the same AP MAC index, so they can be joined safely.
     *
     * @param array<string, mixed> $device
     * @return array<string, string>
     */
    private function localIpInventory(array $device): array
    {
        try {
            $names = snmpwalk_cache_oid($device, 'cLApName', [], 'CISCO-LWAPP-AP-MIB');
            $addresses = snmpwalk_cache_oid($device, 'cLApInetAddress', [], 'CISCO-LWAPP-AP-MIB');
        } catch (Throwable $e) {
            $this->warn((string) $device['hostname'] . ': unable to collect AP local IP addresses: ' . $e->getMessage());
            return [];
        }

        $result = [];
        foreach ($names as $index => $entry) {
            $name = trim((string) ($entry['cLApName'] ?? ''));
            if ($name === '' || ! isset($addresses[$index]['cLApInetAddress'])) {
                continue;
            }

            $ip = $this->decodeInetAddress($addresses[$index]['cLApInetAddress']);
            if ($ip !== null) {
                $result[$name] = $ip;
            }
        }

        return $result;
    }

    private function decodeInetAddress(mixed $value): ?string
    {
        if (is_string($value)) {
            $value = trim($value);

            if (filter_var($value, FILTER_VALIDATE_IP)) {
                return $value;
            }

            // Raw InetAddress values are commonly returned as 4 or 16 binary bytes.
            if (strlen($value) === 4 || strlen($value) === 16) {
                $decoded = @inet_ntop($value);
                return $decoded !== false ? $decoded : null;
            }

            // Accept Net-SNMP style hexadecimal output if a driver returns it verbatim.
            $hex = preg_replace('/^(?:Hex-STRING:\s*)/i', '', $value);
            $hex = preg_replace('/[^0-9a-f]/i', '', (string) $hex);
            if (strlen($hex) === 8 || strlen($hex) === 32) {
                $binary = @hex2bin($hex);
                if ($binary !== false) {
                    $decoded = @inet_ntop($binary);
                    return $decoded !== false ? $decoded : null;
                }
            }
        }

        return null;
    }

    private function normalizeMac(string $mac): string
    {
        $hex = strtolower(preg_replace('/[^0-9a-f]/i', '', $mac) ?? '');
        if (strlen($hex) !== 12) {
            return strtolower(trim($mac));
        }

        return implode(':', str_split($hex, 2));
    }
}
