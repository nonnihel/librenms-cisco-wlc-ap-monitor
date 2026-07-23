# Troubleshooting

## Management page returns 404

Check the registered routes:

```bash
cd /opt/librenms
sudo -u librenms -H php artisan route:list | grep -i cisco-wlc-ap-monitor
```

Restore package registration and clear caches:

```bash
sudo bash /opt/librenms/local-plugins/librenms-cisco-wlc-ap-monitor/tools/ensure-plugin.sh
cd /opt/librenms
sudo -u librenms -H php artisan optimize:clear
sudo -u librenms -H php artisan package:discover
```

Restart PHP-FPM and the web server when routes or views still appear stale:

```bash
sudo systemctl restart php8.3-fpm
sudo systemctl restart nginx
```

Adjust service names for the installed PHP version or Apache environment, then hard-refresh the browser.

## The browser still shows the old plugin after an update

Confirm the new source was actually copied into the installed plugin directory:

```bash
grep -RniE 'View all access points|access-points.*show|MenuEntryHook' \
  /opt/librenms/local-plugins/librenms-cisco-wlc-ap-monitor
```

Then check routes and file timestamps:

```bash
cd /opt/librenms
sudo -u librenms -H php artisan route:list | grep -i cisco-wlc-ap-monitor
find /opt/librenms/local-plugins/librenms-cisco-wlc-ap-monitor \
  -type f -printf '%TY-%Tm-%Td %TH:%TM %p\n' | sort | tail -n 20
```

A successful `update.sh` run does not prove that `git pull` succeeded. Always review the Git output before the update-script output.

## Git reports dubious ownership for `/opt/librenms`

Example:

```text
fatal: detected dubious ownership in repository at '/opt/librenms'
```

This commonly happens when `git pull` is run from a copied plugin directory that is not itself a Git checkout. Git walks upward, finds the LibreNMS repository, and refuses to use it under the current account.

Do not add `/opt/librenms` globally as a safe directory merely to update this plugin. Use a separate checkout outside the LibreNMS tree and rsync it into place:

```bash
cd /tmp
sudo rm -rf librenms-cisco-wlc-ap-monitor-update
sudo -u librenms -H git clone \
  https://github.com/nonnihel/librenms-cisco-wlc-ap-monitor.git \
  librenms-cisco-wlc-ap-monitor-update

sudo rsync -a --delete --exclude='.git' \
  /tmp/librenms-cisco-wlc-ap-monitor-update/ \
  /opt/librenms/local-plugins/librenms-cisco-wlc-ap-monitor/

sudo chown -R librenms:librenms \
  /opt/librenms/local-plugins/librenms-cisco-wlc-ap-monitor

sudo bash /opt/librenms/local-plugins/librenms-cisco-wlc-ap-monitor/update.sh
```

For subsequent updates, run `git pull --ff-only` in the external checkout before the rsync step.

## Migration fails with `Duplicate column name`

Example:

```text
SQLSTATE[42S21]: Column already exists: 1060 Duplicate column name 'location'
```

This means an earlier migration run created one or more columns but failed before Laravel recorded the migration as completed.

Inspect the current table first:

```bash
sudo mysql librenms -e "SHOW COLUMNS FROM cisco_wlc_ap_monitor;"
```

Pull the latest plugin version, which uses idempotent column checks, rsync it into place, and run `update.sh` again. Do not drop populated columns merely to make the migration pass.

Check migration status when needed:

```bash
cd /opt/librenms
sudo -u librenms -H php artisan migrate:status | grep -i cisco_wlc
```

## `plugin:list` is not available

Some LibreNMS versions do not include an `lnms plugin:list` command:

```text
Command "plugin:list" is not defined
```

This is not evidence that the plugin is missing. Verify the installation using:

```bash
cd /opt/librenms
sudo -u librenms -H php artisan route:list | grep -i cisco-wlc-ap-monitor
cat /opt/librenms/composer.plugins.json
sudo bash /opt/librenms/local-plugins/librenms-cisco-wlc-ap-monitor/tools/healthcheck.sh
```

## `plugin:enable` reports `0 plugins enabled`

This may occur when the Composer package is already loaded or when the command expects a different internal plugin identifier. Check routes and health status instead of repeatedly enabling the package.

```bash
sudo -u librenms -H php artisan route:list | grep -i cisco-wlc-ap-monitor
sudo bash /opt/librenms/local-plugins/librenms-cisco-wlc-ap-monitor/tools/healthcheck.sh
```

## No link appears under the Wireless menu

LibreNMS builds the core Wireless dropdown from wireless sensor classes and does not currently expose a package-plugin hook for arbitrary Wireless menu entries.

Use the native dashboard widget as the supported navigation path:

- **View all access points** opens the complete monitor.
- Clicking an AP name opens its detail page.

The standard package menu hook may also appear under the LibreNMS Plugins menu. Avoid patching the LibreNMS core menu template because the local change can be overwritten or conflict during upgrades.

## Widget returns a server error

Inspect the LibreNMS log:

```bash
sudo grep -a -iE 'cisco_wlc|widget|SQLSTATE|exception' \
  /opt/librenms/logs/librenms.log | tail -n 100
```

The widget query qualifies `cisco_wlc_ap_monitor.device_id` to avoid an ambiguous SQL-column error after joining the `devices` table.

## Dashboard widget does not show the new link

Confirm the native widget route exists:

```bash
cd /opt/librenms
sudo -u librenms -H php artisan route:list | grep 'ajax/dash/cisco-wlc-ap-monitor'
```

Clear caches and restart the web stack:

```bash
sudo -u librenms -H php artisan optimize:clear
sudo -u librenms -H php artisan package:discover
sudo systemctl restart php8.3-fpm
sudo systemctl restart nginx
```

Hard-refresh the browser. Removing and re-adding the widget can also force LibreNMS to reload its widget markup and settings.

## AP detail route is missing

The route list should contain a GET route similar to:

```text
cisco-wlc-ap-monitor/access-points/{id}
```

Check with:

```bash
cd /opt/librenms
sudo -u librenms -H php artisan route:list | grep -i 'cisco-wlc-ap-monitor/access-points'
```

If it is absent, the installed source is older than the GitHub version. Follow the external-checkout and rsync update workflow in [UPGRADE.md](UPGRADE.md).

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
sudo -u librenms -H ./lnms cisco-wlc-ap:poll \
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

## SNMP MIB warnings appear before valid AP data

Cisco MIB dependency or parser warnings can appear while the requested OID still returns valid data. Treat warnings and transport/authentication failures separately.

Use numeric or single-column walks to reduce output. Never include the real SNMP community string or SNMPv3 credentials in shared command output.

For the AP local address, `CISCO-LWAPP-AP-MIB::cLApInetAddress` may be returned as four binary bytes, for example:

```text
Hex-STRING: 0A 64 63 1B
```

This represents IPv4 address `10.100.99.27`. Net-SNMP may sometimes print the same bytes as escaped or unreadable text instead of `Hex-STRING`; the plugin must decode the raw four-byte value rather than trusting the display format.

## `validate.php` reports an extra migration

Plugin migrations may appear as extra migrations. This is expected because the package loads its migrations into the LibreNMS migration repository.

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
