# Cisco WLC AP Monitor for LibreNMS

A LibreNMS package plugin for persistent monitoring of Cisco wireless access points registered to Cisco Wireless LAN Controllers (WLCs).

The plugin solves a practical monitoring gap: when an AP disappears from a Cisco WLC, LibreNMS may no longer retain enough information in its normal access-point inventory to identify the exact AP that went offline. This plugin maintains its own persistent AP inventory, marks missing APs as **down**, and reports the AP name through a native LibreNMS service check and alert.

## What it does

- Tracks Cisco APs learned from one or more Cisco WLC devices in LibreNMS.
- Detects the exact AP that is online, offline, ignored, or retired.
- Keeps historical AP records even when an AP disappears from the controller.
- Provides a LibreNMS web page for AP state management.
- Supports **Ignore**, **Retire**, **Restore**, and **Delete** actions.
- Includes a compact dashboard widget.
- Includes a Nagios-compatible service check for LibreNMS Services.
- Supports LibreNMS Alert Rules, Alert Templates, Operations, Transports, and recovery notifications.
- Includes installation, upgrade, backup, restore, and server-migration scripts.
- Uses LibreNMS package-plugin registration through `lnms plugin:add` and `composer.plugins.json`.

## Why this plugin is useful

A simple WLC device-up check only confirms that the controller responds. It does not tell you whether an individual AP has disappeared.

This plugin polls the AP inventory, stores it in a dedicated table, and produces service output such as:

```text
CRITICAL - 1 AP(s) down on 192.0.2.10: AP-FLOOR-2 | down=1
```

Recovery output returns to:

```text
OK - all monitored APs are online on 192.0.2.10 | down=0
```

That output can be delivered through any LibreNMS alert transport, including email, Microsoft Teams, Slack, PagerDuty, webhooks, and others supported by LibreNMS.

## Requirements

- A working LibreNMS installation.
- Cisco WLC devices already added to LibreNMS and detected as `ciscowlc`.
- PHP 8.2 or newer.
- MariaDB or MySQL used by LibreNMS.
- LibreNMS Services support.
- Standard Linux utilities including Git, cron, `rsync`, and `sudo`.

The project was developed and tested with LibreNMS 26.7/26.8 development builds, PHP 8.3, MariaDB 10.6, and Cisco AireOS 8.10. Other compatible versions may also work.

## Quick installation

```bash
cd /opt
sudo git clone https://github.com/nonnihel/librenms-cisco-wlc-ap-monitor.git
cd librenms-cisco-wlc-ap-monitor
sudo bash install.sh
```

The installer copies the plugin to:

```text
/opt/librenms/local-plugins/librenms-cisco-wlc-ap-monitor
```

It then registers the local path repository, installs the package using LibreNMS `lnms plugin:add`, runs the migration, installs the service-check script, and enables scheduled AP polling.

Open the management page at:

```text
https://YOUR-LIBRENMS/cisco-wlc-ap-monitor
```

Run a manual poll:

```bash
cd /opt/librenms
sudo -u librenms -H php lnms cisco-wlc-ap:poll --no-interaction
```

Run the service check manually:

```bash
sudo -u librenms -H \
  /usr/lib/nagios/plugins/check_cisco_wlc_ap_monitor.php \
  -H WLC_HOSTNAME_OR_IP \
  --device-id LIBRENMS_DEVICE_ID
```

## Alerting

After installation, add a LibreNMS Service to each WLC:

- **Type:** `cisco_wlc_ap_monitor.php`
- **IP/Hostname:** the WLC hostname or IP address
- **Parameters:** `--device-id <LibreNMS device ID>`

Create a LibreNMS Alert Rule using:

```text
services.service_status = 2
AND services.service_type = "cisco_wlc_ap_monitor.php"
```

Enable recovery alerts so LibreNMS sends a second notification when all monitored APs are online again.

Complete alerting instructions, including an alert template example, are available in [docs/ALERTING.md](docs/ALERTING.md).

## Dashboard widget

Add a LibreNMS Notes widget containing:

```html
<iframe
  src="/cisco-wlc-ap-monitor/widget?device_id=290&limit=10&refresh=60"
  style="width:100%;height:100%;min-height:330px;border:0;">
</iframe>
```

Replace `290` with the LibreNMS device ID of the WLC. See [docs/DASHBOARD.md](docs/DASHBOARD.md).

## AP lifecycle states

- **Up** — the AP is currently present on the WLC.
- **Down** — the AP was previously known but is no longer present on the WLC.
- **Ignored** — the AP remains visible but is excluded from service alerts.
- **Retired** — the AP has been intentionally decommissioned and remains excluded until restored.

Deleting an AP removes its plugin history. If the AP is still online, it will be discovered again during the next poll.

## Documentation

- [Installation](docs/INSTALL.md)
- [Alerting](docs/ALERTING.md)
- [Dashboard widget](docs/DASHBOARD.md)
- [Updating LibreNMS and the plugin](docs/UPGRADE.md)
- [Moving to a new LibreNMS server](docs/MIGRATION.md)
- [Troubleshooting](docs/TROUBLESHOOTING.md)
- [GitHub publishing and releases](docs/GITHUB.md)
- [Contributing](CONTRIBUTING.md)
- [Security policy](SECURITY.md)

## Important update note

Because local package plugins are installed through Composer, LibreNMS may show `composer.json` and `composer.lock` as locally modified. Do not blindly run `./scripts/github-remove` or restore those files without understanding that doing so may remove the plugin package. Follow [docs/UPGRADE.md](docs/UPGRADE.md) before upgrading or cleaning the LibreNMS Git working tree.

## License

This project is licensed under the GNU General Public License v3.0 or later.
