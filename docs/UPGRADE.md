# Updating LibreNMS and this plugin

## Why the package survives updates

The plugin source is stored under `/opt/librenms/local-plugins`, while LibreNMS records installed packages in `/opt/librenms/composer.plugins.json` through `lnms plugin:add`. It does not modify `LibreNMS/OS/Ciscowlc.php` or other Cisco WLC discovery code.

During package installation, Composer will also show the plugin in the active LibreNMS `composer.json` and `composer.lock`. LibreNMS uses `composer.plugins.json` to restore plugin packages during its Composer workflow. Do not run `scripts/github-remove` merely to remove these expected plugin-related changes.

## Before a LibreNMS update

Create a plugin backup and validate LibreNMS:

```bash
sudo bash /opt/librenms/local-plugins/librenms-cisco-wlc-ap-monitor/tools/backup-state.sh
cd /opt/librenms
sudo -u librenms -H ./validate.php
```

Keep the generated backup outside `/opt/librenms`.

## Update LibreNMS

Use the normal supported LibreNMS update process for your installation. After the update completes, run:

```bash
sudo bash /opt/librenms/local-plugins/librenms-cisco-wlc-ap-monitor/tools/ensure-plugin.sh
sudo bash /opt/librenms/local-plugins/librenms-cisco-wlc-ap-monitor/tools/healthcheck.sh
```

Then test the poller and routes:

```bash
cd /opt/librenms
sudo -u librenms -H ./lnms cisco-wlc-ap:poll --no-interaction
sudo -u librenms -H php artisan route:list | grep -i cisco-wlc-ap-monitor
```

Test the service check:

```bash
sudo -u librenms -H \
  /usr/lib/nagios/plugins/check_cisco_wlc_ap_monitor.php \
  -H WLC_HOSTNAME_OR_IP \
  --device-id DEVICE_ID
```

## Recommended plugin update workflow

Do not run `git pull` from inside `/opt/librenms/local-plugins/librenms-cisco-wlc-ap-monitor` unless that directory is a real Git checkout with correct ownership. On many LibreNMS installations the plugin directory is only an rsync copy, and Git may instead detect `/opt/librenms` as the enclosing repository and fail with:

```text
fatal: detected dubious ownership in repository at '/opt/librenms'
```

The safest repeatable method is to keep a separate checkout outside `/opt/librenms`, update that checkout, and then rsync it into the installed plugin directory.

### 1. Create or refresh the external checkout

First installation of the update checkout:

```bash
cd /tmp
sudo rm -rf librenms-cisco-wlc-ap-monitor-update
sudo -u librenms -H git clone \
  https://github.com/nonnihel/librenms-cisco-wlc-ap-monitor.git \
  librenms-cisco-wlc-ap-monitor-update
```

For later updates:

```bash
cd /tmp/librenms-cisco-wlc-ap-monitor-update
sudo -u librenms -H git pull --ff-only
```

A persistent checkout such as `/opt/src/librenms-cisco-wlc-ap-monitor` may be used instead of `/tmp`.

### 2. Copy the updated source into LibreNMS

```bash
sudo rsync -a --delete \
  --exclude='.git' \
  /tmp/librenms-cisco-wlc-ap-monitor-update/ \
  /opt/librenms/local-plugins/librenms-cisco-wlc-ap-monitor/

sudo chown -R librenms:librenms \
  /opt/librenms/local-plugins/librenms-cisco-wlc-ap-monitor
```

The `--delete` option intentionally removes files that no longer exist upstream. Confirm both paths carefully before running the command.

### 3. Run the plugin updater

```bash
cd /opt/librenms/local-plugins/librenms-cisco-wlc-ap-monitor
sudo bash update.sh
```

The update script preserves the AP history table, runs pending migrations, refreshes package discovery, clears Laravel caches, reinstalls the service check, verifies package registration, and runs the plugin health check.

### 4. Clear runtime caches and restart the web stack

`update.sh` already clears Laravel caches, but after routes, views, controllers, or dashboard widgets change, run the following explicitly:

```bash
cd /opt/librenms
sudo -u librenms -H php artisan optimize:clear
sudo -u librenms -H php artisan package:discover
sudo systemctl restart php8.3-fpm
sudo systemctl restart nginx
```

Adjust the PHP-FPM service name if another PHP version is installed. Restart Apache instead of nginx on Apache-based systems.

Then hard-refresh the browser with `Ctrl+Shift+R` or sign out and back in.

### 5. Verify routes and polling

```bash
cd /opt/librenms
sudo -u librenms -H php artisan route:list | grep -i cisco-wlc-ap-monitor
sudo -u librenms -H ./lnms cisco-wlc-ap:poll --device=DEVICE_ID
```

Expected routes include the management page, AP detail page, action endpoint, iframe widget endpoint, and native dashboard widget endpoint.

Verify the database and stored inventory when needed:

```bash
sudo mysql librenms -e "
SHOW COLUMNS FROM cisco_wlc_ap_monitor;
SELECT ap_name,state,radio_mac,client_count,radio_count,channels,max_utilization,last_seen_at
FROM cisco_wlc_ap_monitor
ORDER BY ap_name
LIMIT 20;
"
```

## Migration recovery

A failed migration can leave some columns created while the migration itself remains unrecorded. A later run may then fail with an error such as:

```text
SQLSTATE[42S21]: Column already exists: 1060 Duplicate column name 'location'
```

Plugin migrations must therefore be idempotent and check `Schema::hasColumn()` before adding or dropping optional inventory columns.

After pulling a migration fix, repeat the external-checkout, rsync, ownership, and `update.sh` procedure. Do not manually drop an existing column unless its contents have been backed up and the migration specifically requires it.

Inspect the table before making any manual database change:

```bash
sudo mysql librenms -e "SHOW COLUMNS FROM cisco_wlc_ap_monitor;"
```

## Plugin commands vary by LibreNMS version

Some LibreNMS versions provide `plugin:add`, `plugin:enable`, `plugin:disable`, and `plugin:remove`, but do not provide `plugin:list`.

Do not treat this as a plugin failure:

```text
Command "plugin:list" is not defined
```

Use route discovery, `composer.plugins.json`, the health check, and the plugin database table to verify installation instead:

```bash
sudo -u librenms -H php artisan route:list | grep -i cisco-wlc-ap-monitor
cat /opt/librenms/composer.plugins.json
sudo bash /opt/librenms/local-plugins/librenms-cisco-wlc-ap-monitor/tools/healthcheck.sh
```

`lnms plugin:enable cisco-wlc-ap-monitor` may report `0 plugins enabled` when the package is already loaded through Composer or when the internal plugin identifier differs. The presence of working routes and a successful health check is a better verification signal.

## Navigation and dashboard access

The supported LibreNMS package hook places plugin entries under the LibreNMS Plugins menu. LibreNMS does not currently expose a package hook for inserting arbitrary entries directly into the core Wireless dropdown.

The native dashboard widget therefore provides the preferred entry point:

- **View all access points** opens the complete AP monitor.
- Clicking an AP name opens the AP detail page.

Do not patch the LibreNMS core menu template unless you are prepared to maintain that local change across LibreNMS updates.

## If routes disappear

Run:

```bash
sudo bash /opt/librenms/local-plugins/librenms-cisco-wlc-ap-monitor/tools/ensure-plugin.sh
sudo -u librenms -H php artisan optimize:clear
sudo -u librenms -H php artisan package:discover
```

The script checks whether the widget route exists and calls `lnms plugin:add` only when registration is missing.

## Modified Composer files warning

`validate.php` can report `composer.json` and `composer.lock` as modified because the local plugin is an active Composer package. Do not blindly restore those files or run `./scripts/github-remove`; doing so can remove the active plugin registration. Take a backup first and be prepared to run `ensure-plugin.sh` afterward.

## Expected migration warning

LibreNMS validation may report plugin migrations as extra migrations. This is expected because the package loads its migrations into the LibreNMS migration repository.

## Security notes

Never include a real SNMP community string, SNMPv3 credential, password, or token in shell transcripts, screenshots, Git commits, issues, or documentation. Replace secrets with placeholders before sharing output. Rotate any credential that has been disclosed.