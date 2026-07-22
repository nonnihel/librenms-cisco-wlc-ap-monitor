# Troubleshooting

## Management page returns 404

Check the registered routes:

```bash
cd /opt/librenms
sudo -u librenms -H php artisan route:list | grep cisco-wlc-ap-monitor
```

Restore package registration:

```bash
sudo bash /opt/librenms/local-plugins/librenms-cisco-wlc-ap-monitor/tools/ensure-plugin.sh
```

## Widget returns a server error

Inspect the LibreNMS log:

```bash
sudo grep -a -iE 'cisco_wlc|widget|SQLSTATE|exception' \
  /opt/librenms/logs/librenms.log | tail -n 100
```

The widget query qualifies `cisco_wlc_ap_monitor.device_id` to avoid an ambiguous SQL-column error after joining the `devices` table.

## Service says `UNKNOWN - --device-id is required`

LibreNMS automatically inserts `-H`. Confirm the installed check accepts that option:

```bash
grep "getopt('H:'" /usr/lib/nagios/plugins/check_cisco_wlc_ap_monitor.php
```

Reinstall the check from the current plugin source:

```bash
sudo bash /opt/librenms/local-plugins/librenms-cisco-wlc-ap-monitor/update.sh
```

## The plugin says all APs are online after one was unplugged

Wait until the AP disappears from the WLC AP summary, then run:

```bash
cd /opt/librenms
sudo -u librenms -H php lnms cisco-wlc-ap:poll \
  --device=DEVICE_ID \
  --no-interaction
```

Check the plugin table:

```bash
sudo mysql librenms -e "
SELECT device_id,ap_name,state,last_seen_at,down_since
FROM cisco_wlc_ap_monitor
ORDER BY device_id,state,ap_name;
"
```

## Service remains OK while the management page shows a down AP

Run the service check manually and confirm the same device ID is used:

```bash
sudo -u librenms -H \
  /usr/lib/nagios/plugins/check_cisco_wlc_ap_monitor.php \
  -H WLC_HOSTNAME_OR_IP \
  --device-id DEVICE_ID
```

Then force a LibreNMS service poll:

```bash
cd /opt/librenms
sudo -u librenms -H ./check-services.php -d
```

## `validate.php` reports an extra migration

The plugin migration may appear as an extra migration. This is expected because the package loads its migration into the LibreNMS migration repository.

## `validate.php` reports modified Composer files

The package can appear in LibreNMS `composer.json` and `composer.lock` after `lnms plugin:add`. Do not blindly run `./scripts/github-remove` or restore those files; doing so may remove the plugin package. Follow [UPGRADE.md](UPGRADE.md).

## Scheduler is not running

This is a LibreNMS system issue separate from the plugin cron. Follow the fix shown by `validate.php`, commonly:

```bash
sudo cp \
  /opt/librenms/dist/librenms-scheduler.service \
  /opt/librenms/dist/librenms-scheduler.timer \
  /etc/systemd/system/

sudo systemctl daemon-reload
sudo systemctl enable --now librenms-scheduler.timer
```

## Logs

```bash
tail -f /opt/librenms/logs/cisco-wlc-ap-monitor.log
```

Run the complete health check:

```bash
sudo bash /opt/librenms/local-plugins/librenms-cisco-wlc-ap-monitor/tools/healthcheck.sh
```
