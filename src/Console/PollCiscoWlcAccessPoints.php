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
    private const LOCAL_IP_OID = '.1.3.6.1.4.1.9.9.513.1.1.10.1.4';

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
        $localIps = $this->localIpInventory($device, $hostname);
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

        $this->info("{$hostname}: " . count($inventory) . ' APs currently online; ' . count($localIps) . ' local IP addresses collected.');
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

    /**
     * Use SnmpQuery for AP names, but collect InetAddress values from a raw
     * hexadecimal Net-SNMP walk. Text parsers may strip leading/trailing control
     * octets such as 0A and 0D from binary IPv4 addresses.
     *
     * @param array<string, mixed> $deviceRow
     * @return array<string, string>
     */
    private function localIpInventory(array $deviceRow, string $hostname): array
    {
        try {
            $device = Device::query()->findOrFail((int) $deviceRow['device_id']);
            $namesResponse = SnmpQuery::device($device)
                ->numericIndex()
                ->walk('CISCO-LWAPP-AP-MIB::cLApName');

            if (! $namesResponse->isValid()) {
                throw new \RuntimeException('cLApName walk failed: ' . $namesResponse->getErrorMessage());
            }

            $names = $this->valuesByNumericIndex($namesResponse->values());
            $addresses = $this->rawHexInetAddresses($deviceRow);
        } catch (Throwable $e) {
            $this->warn("{$hostname}: unable to collect AP local IP addresses: {$e->getMessage()}");
            return [];
        }

        $result = [];
        foreach ($names as $index => $nameValue) {
            $name = trim((string) $nameValue, " \t\n\r\0\x0B\"");
            $ip = $addresses[$index] ?? null;
            if ($name !== '' && is_string($ip) && filter_var($ip, FILTER_VALIDATE_IP)) {
                $result[$name] = $ip;
            }
        }

        return $result;
    }

    /** @param array<string, mixed> $device */
    private function rawHexInetAddresses(array $device): array
    {
        $commonHelpers = base_path('includes/common.php');
        if (! function_exists('external_exec') && is_file($commonHelpers)) {
            require_once $commonHelpers;
        }

        $snmpHelpers = base_path('includes/snmp.inc.php');
        if (! function_exists('gen_snmpwalk_cmd') && is_file($snmpHelpers)) {
            require_once $snmpHelpers;
        }

        if (! function_exists('gen_snmpwalk_cmd')) {
            throw new \RuntimeException('LibreNMS SNMP command builder is unavailable.');
        }

        $command = \gen_snmpwalk_cmd(
            $device,
            self::LOCAL_IP_OID,
            ['-On', '-Ox']
        );

        $process = new Process($command, base_path());
        $process->setTimeout((int) $this->option('timeout'));
        $process->run();

        if (! $process->isSuccessful()) {
            throw new \RuntimeException('raw cLApInetAddress walk failed: ' . trim($process->getErrorOutput() ?: $process->getOutput()));
        }

        $addresses = [];
        $base = preg_quote(self::LOCAL_IP_OID, '/');
        foreach (preg_split('/\R/', $process->getOutput()) ?: [] as $line) {
            if (preg_match('/^' . $base . '\.(\d+(?:\.\d+){5})\s+=\s+Hex-STRING:\s+((?:[0-9A-Fa-f]{2}\s*)+)$/', trim($line), $matches) !== 1) {
                continue;
            }

            $hex = preg_replace('/\s+/', '', $matches[2]);
            $binary = is_string($hex) ? @hex2bin($hex) : false;
            if ($binary === false || ! in_array(strlen($binary), [4, 16], true)) {
                continue;
            }

            $ip = @inet_ntop($binary);
            if ($ip !== false) {
                $addresses[$matches[1]] = $ip;
            }
        }

        return $addresses;
    }

    /** @param array<string, mixed> $values */
    private function valuesByNumericIndex(array $values): array
    {
        $indexed = [];
        foreach ($values as $oid => $value) {
            if (preg_match('/\.(\d+(?:\.\d+){5})$/', (string) $oid, $matches) === 1) {
                $indexed[$matches[1]] = $value;
            }
        }

        return $indexed;
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
