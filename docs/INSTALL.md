# New installation

## Prerequisites

Confirm that Cisco WLC devices are already monitored by LibreNMS:

```bash
sudo mysql librenms -e "SELECT device_id,hostname,sysName,os FROM devices WHERE os='ciscowlc';"
```

Confirm that APs exist in LibreNMS:

```bash
sudo mysql librenms -e "SELECT device_id,name,radio_number,deleted FROM access_points LIMIT 20;"
```

## Install from GitHub

```bash
cd /opt
sudo git clone https://github.com/nonnihel/librenms-cisco-wlc-ap-monitor.git
cd librenms-cisco-wlc-ap-monitor
sudo ./install.sh
```

The installer:

1. Copies the source to `/opt/librenms/local-plugins/librenms-cisco-wlc-ap-monitor`.
2. Configures a local Composer path repository for the `librenms` account.
3. Runs `lnms plugin:add`, which records the plugin in `composer.plugins.json`.
4. Runs the migration.
5. Installs the Nagios-compatible service check.
6. Creates `/etc/cron.d/librenms-cisco-wlc-ap-monitor`.
7. Seeds the current AP inventory.

## Verify the installation

```bash
cd /opt/librenms
sudo -u librenms -H php artisan route:list | grep cisco-wlc-ap-monitor
sudo -u librenms -H php lnms cisco-wlc-ap:poll --no-interaction
sudo /opt/librenms/local-plugins/librenms-cisco-wlc-ap-monitor/tools/healthcheck.sh
```

Expected routes:

```text
GET|HEAD  cisco-wlc-ap-monitor
GET|HEAD  cisco-wlc-ap-monitor/widget
POST      cisco-wlc-ap-monitor/access-points/{id}/action
```

Open:

```text
https://YOUR-LIBRENMS/cisco-wlc-ap-monitor
```

## Add the LibreNMS Service

For each WLC, add a service:

- Type: `cisco_wlc_ap_monitor.php`
- Description: `Cisco WLC Access Point Monitor`
- Parameters: `--device-id DEVICE_ID`

Manual test:

```bash
sudo -u librenms -H \
  /usr/lib/nagios/plugins/check_cisco_wlc_ap_monitor.php \
  -H WLC_HOSTNAME_OR_IP \
  --device-id DEVICE_ID
```

Continue with [ALERTING.md](ALERTING.md) and [DASHBOARD.md](DASHBOARD.md).

## Validation warning

`validate.php` can report the plugin migration as an extra migration. That warning is expected for this package and does not indicate failed AP monitoring.
