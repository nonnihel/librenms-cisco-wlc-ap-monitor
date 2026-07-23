# Dashboard widget

The plugin now registers a native LibreNMS dashboard widget named:

```text
Cisco WLC AP Monitor
```

No Notes widget or iframe is required.

## Add the native widget

1. Update the plugin to the latest version.
2. Run the plugin update script and clear LibreNMS caches:

```bash
cd /opt/librenms-cisco-wlc-ap-monitor
sudo git pull
sudo bash update.sh
```

3. Open the LibreNMS dashboard.
4. Enter dashboard edit mode.
5. Open **Add Widgets**.
6. Select **Cisco WLC AP Monitor**.
7. Use the edit icon on the widget to configure:
   - a specific Cisco WLC, or all Cisco WLC devices;
   - the maximum number of down APs displayed;
   - whether a green healthy message is displayed when no APs are down;
   - the normal LibreNMS widget refresh interval.

The widget displays totals for Up, Down, Ignored and Retired APs. When APs are down, it lists the AP name, controller and how long the AP has been down. Each AP name links to the full plugin management page.

## Verify widget registration

```bash
cd /opt/librenms
sudo -u librenms -H php artisan route:list | grep 'ajax/dash/cisco-wlc-ap-monitor'
```

Expected route:

```text
GET|POST  ajax/dash/cisco-wlc-ap-monitor
```

The widget appears in LibreNMS because dashboard widget types are discovered from authenticated routes registered below the `ajax/dash` prefix.

## Legacy compact page

The plugin still exposes the older compact authenticated page:

```text
/cisco-wlc-ap-monitor/widget
```

Supported query parameters:

- `device_id=290` — filter to one Cisco WLC.
- `limit=10` — maximum number of down AP rows, from 1 to 50.
- `refresh=60` — browser refresh interval in seconds, from 15 to 3600.

This route may be useful for direct display or older installations, but the native LibreNMS widget is now the recommended method.

## Troubleshooting

If the widget does not appear after updating:

```bash
cd /opt/librenms
sudo -u librenms -H php artisan package:discover
sudo -u librenms -H php artisan optimize:clear
sudo -u librenms -H php artisan route:list | grep cisco-wlc-ap-monitor
```

Also verify that the plugin package remains present in `/opt/librenms/composer.plugins.json`.

For application errors, inspect:

```bash
sudo grep -a -iE 'cisco_wlc|widget|SQLSTATE|exception' \
  /opt/librenms/logs/librenms.log | tail -n 100
```
