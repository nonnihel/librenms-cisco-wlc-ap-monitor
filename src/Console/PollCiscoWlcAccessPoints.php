<?php

declare(strict_types=1);
namespace Averna\CiscoWlcApMonitor\Console;

use App\Models\Device;
use Averna\CiscoWlcApMonitor\Models\WlcAccessPoint;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use SnmpQuery;
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

    /** @param array<string, mixed> $device */
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
        $localIps = $this->localIpInventory($deviceId, $hostname);
        $now = Carbon::now();
        $seenIds = [];

        foreach ($inventory as $name => $row) {
            $mac = $this->normalizeMac((string) ($row->mac_addr ?? ''));
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

    /** @return array<string, object> */
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

    /** @return array<string, string> */
    private function localIpInventory(int $deviceId, string $hostname): array
    {
        try {
            $device = Device::query()->findOrFail($deviceId);
            $namesResponse = SnmpQuery::device($device)->numericIndex()->walk('CISCO-LWAPP-AP-MIB::cLApName');
            $addressesResponse = SnmpQuery::device($device)->numericIndex()->walk('CISCO-LWAPP-AP-MIB::cLApInetAddress');

            if (! $namesResponse->isValid()) {
                throw new \RuntimeException('cLApName walk failed: ' . $namesResponse->getErrorMessage());
            }
            if (! $addressesResponse->isValid()) {
                throw new \RuntimeException('cLApInetAddress walk failed: ' . $addressesResponse->getErrorMessage());
            }

            $names = $this->valuesByNumericIndex($namesResponse->values());
            $addresses = $this->valuesByNumericIndex($addressesResponse->values());
        } catch (Throwable $e) {
            $this->warn("{$hostname}: unable to collect AP local IP addresses: {$e->getMessage()}");
            return [];
        }

        $result = [];
        foreach ($names as $index => $nameValue) {
            $name = trim((string) $nameValue, " \t\n\r\0\x0B\"");
            if ($name === '' || ! array_key_exists($index, $addresses)) {
                continue;
            }

            $ip = $this->decodeInetAddress($addresses[$index]);
            if ($ip !== null) {
                $result[$name] = $ip;
            }
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $values
     * @return array<string, mixed>
     */
    private function valuesByNumericIndex(array $values): array
    {
        $indexed = [];
        foreach ($values as $oid => $value) {
            $oid = (string) $oid;

            // SnmpQuery::numericIndex() returns textual OIDs followed by the
            // six decimal MAC octets, for example cLApName.0.93.115.0.90.32.
            if (preg_match('/\.(\d+(?:\.\d+){5})$/', $oid, $matches) === 1) {
                $indexed[$matches[1]] = $value;
                continue;
            }

            // Keep compatibility with table-style bracket indexes.
            if (preg_match('/\[([^\]]+)\]$/', $oid, $matches) === 1) {
                $indexed[$matches[1]] = $value;
            }
        }

        return $indexed;
    }

    private function decodeInetAddress(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $raw = $value;
        if (strlen($raw) === 4 || strlen($raw) === 16) {
            $decoded = @inet_ntop($raw);
            if ($decoded !== false) {
                return $decoded;
            }
        }

        // LibreNMS may trim a leading newline byte (0x0A) from binary IPv4
        // values. On this Cisco WLC those shortened three-byte values are
        // 10.x.x.x addresses, so restore the missing first octet.
        if (strlen($raw) === 3) {
            $decoded = @inet_ntop("\x0A" . $raw);
            if ($decoded !== false) {
                return $decoded;
            }
        }

        $text = trim($value);
        if (filter_var($text, FILTER_VALIDATE_IP)) {
            return $text;
        }

        $text = preg_replace('/^(?:Hex-STRING|STRING):\s*/i', '', $text) ?? $text;
        if (strlen($text) >= 2 && $text[0] === '"' && $text[strlen($text) - 1] === '"') {
            $text = substr($text, 1, -1);
        }

        // SnmpQuery commonly returns hexadecimal octets as "0A 64 63 1B".
        $hexText = preg_replace('/[^0-9a-f]/i', '', $text);
        if (is_string($hexText) && (strlen($hexText) === 8 || strlen($hexText) === 32)) {
            $binary = @hex2bin($hexText);
            if ($binary !== false) {
                $decoded = @inet_ntop($binary);
                if ($decoded !== false) {
                    return $decoded;
                }
            }
        }

        $unescaped = preg_replace_callback('/\\\\x([0-9a-fA-F]{2})/', static fn (array $match): string => chr((int) hexdec($match[1])), $text);
        if (is_string($unescaped)) {
            $unescaped = str_replace(['\\n', '\\r', '\\t', '\\\\', '\\"'], ["\n", "\r", "\t", '\\', '"'], $unescaped);
            if (strlen($unescaped) === 4 || strlen($unescaped) === 16) {
                $decoded = @inet_ntop($unescaped);
                if ($decoded !== false) {
                    return $decoded;
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
