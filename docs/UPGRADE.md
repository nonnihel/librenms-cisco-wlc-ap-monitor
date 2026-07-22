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
sudo -u librenms -H php lnms cisco-wlc-ap:poll --no-interaction
sudo -u librenms -H php artisan route:list | grep cisco-wlc-ap-monitor
```

Test the service check:

```bash
sudo -u librenms -H \
  /usr/lib/nagios/plugins/check_cisco_wlc_ap_monitor.php \
  -H WLC_HOSTNAME_OR_IP \
  --device-id DEVICE_ID
```

## Update the plugin from GitHub

Clone or pull the latest source outside `/opt/librenms`, then run:

```bash
cd /path/to/librenms-cisco-wlc-ap-monitor
git pull
sudo bash update.sh
```

The update script preserves the database table and AP history, refreshes the plugin source, runs migrations, reinstalls the service check, clears Laravel caches, and validates routes.

## If routes disappear

Run:

```bash
sudo bash /opt/librenms/local-plugins/librenms-cisco-wlc-ap-monitor/tools/ensure-plugin.sh
```

The script checks whether the widget route exists and calls `lnms plugin:add` only when registration is missing.

## Modified Composer files warning

`validate.php` can report `composer.json` and `composer.lock` as modified because the local plugin is an active Composer package. Do not blindly restore those files or run `./scripts/github-remove`; doing so can remove the active plugin registration. Take a backup first and be prepared to run `ensure-plugin.sh` afterward.

## Expected migration warning

LibreNMS validation may report `2026_07_21_000000_create_cisco_wlc_ap_monitor_table` as an extra migration. This is expected because the package loads its migration into the LibreNMS migration repository.
