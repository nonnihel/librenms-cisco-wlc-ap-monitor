# Move to a new LibreNMS server

The migration has three parts:

1. Install and validate LibreNMS on the new server.
2. Install this plugin.
3. Move the persistent AP state and recreate or migrate LibreNMS service and alert configuration.

## Old server: create a backup

```bash
sudo bash /opt/librenms/local-plugins/librenms-cisco-wlc-ap-monitor/tools/backup-state.sh
```

The command produces a timestamped `.tar.gz` archive containing:

- `cisco_wlc_ap_monitor.sql`
- a plugin source snapshot
- a `composer.plugins.json` snapshot
- cron and service-check copies
- a TSV report of matching LibreNMS service rows

Copy the archive securely to the new server. Treat it as internal infrastructure data because it contains controller names, AP names, device IDs, and service configuration details.

## New server: prepare LibreNMS

Install LibreNMS normally and add the Cisco WLC devices. Confirm the new device IDs:

```bash
sudo mysql librenms -e "SELECT device_id,hostname,sysName,os FROM devices WHERE os='ciscowlc';"
```

## Install the plugin

```bash
cd /opt
sudo git clone https://github.com/nonnihel/librenms-cisco-wlc-ap-monitor.git
cd librenms-cisco-wlc-ap-monitor
sudo bash install.sh
```

## Restore AP history

Temporarily disable the plugin cron:

```bash
sudo mv \
  /etc/cron.d/librenms-cisco-wlc-ap-monitor \
  /etc/cron.d/librenms-cisco-wlc-ap-monitor.disabled
```

Restore the backup:

```bash
sudo bash \
  /opt/librenms/local-plugins/librenms-cisco-wlc-ap-monitor/tools/restore-state.sh \
  /path/to/librenms-cisco-wlc-ap-monitor-YYYYMMDD-HHMMSS.tar.gz
```

## Device ID changes

The plugin table references the LibreNMS `device_id`. If a WLC has a different device ID on the new server, update the restored rows before polling:

```bash
sudo mysql librenms -e "
UPDATE cisco_wlc_ap_monitor
SET device_id=NEW_ID
WHERE device_id=OLD_ID;
"
```

Confirm every mapping before making the change.

## Restore polling

```bash
sudo mv \
  /etc/cron.d/librenms-cisco-wlc-ap-monitor.disabled \
  /etc/cron.d/librenms-cisco-wlc-ap-monitor

cd /opt/librenms
sudo -u librenms -H php lnms cisco-wlc-ap:poll --no-interaction
```

## Service and alert configuration

LibreNMS Service, Alert Rule, Alert Template, Transport, and Operation records belong to the main LibreNMS database.

- If the whole LibreNMS database was migrated, these records normally move with it.
- If only the plugin state was migrated, recreate the Service and Alert configuration using [ALERTING.md](ALERTING.md).
- Review each Service parameter and update `--device-id` when device IDs changed.

## Final validation

```bash
sudo bash /opt/librenms/local-plugins/librenms-cisco-wlc-ap-monitor/tools/healthcheck.sh

sudo -u librenms -H \
  /usr/lib/nagios/plugins/check_cisco_wlc_ap_monitor.php \
  -H WLC_HOSTNAME_OR_IP \
  --device-id DEVICE_ID
```

Open the management page and dashboard widget, then test one controlled AP outage and recovery before decommissioning the old server.
