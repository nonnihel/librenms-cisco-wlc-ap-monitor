# Dashboard widget

The plugin exposes a compact authenticated page:

```text
/cisco-wlc-ap-monitor/widget
```

Supported query parameters:

- `device_id=290` — filter the widget to one Cisco WLC.
- `limit=10` — maximum number of down AP rows, from 1 to 50.
- `refresh=60` — browser refresh interval in seconds, from 15 to 3600.

## Add the widget to a LibreNMS dashboard

1. Open the LibreNMS dashboard.
2. Enter dashboard edit mode.
3. Add a **Notes** widget.
4. Paste:

```html
<iframe
  src="/cisco-wlc-ap-monitor/widget?device_id=290&limit=10&refresh=60"
  style="width:100%;height:100%;min-height:330px;border:0;">
</iframe>
```

5. Replace `290` with the LibreNMS device ID of the WLC.
6. Save and resize the Notes widget.

The iframe is same-origin and uses the current authenticated LibreNMS session. No separate credentials are embedded in the dashboard HTML.

## Show all WLCs

Remove the `device_id` parameter:

```html
<iframe
  src="/cisco-wlc-ap-monitor/widget?limit=20&refresh=60"
  style="width:100%;height:100%;min-height:420px;border:0;">
</iframe>
```

## Direct tests

```text
https://YOUR-LIBRENMS/cisco-wlc-ap-monitor
https://YOUR-LIBRENMS/cisco-wlc-ap-monitor/widget?device_id=290
```

If either URL returns 404, run:

```bash
sudo bash /opt/librenms/local-plugins/librenms-cisco-wlc-ap-monitor/tools/ensure-plugin.sh
```

If the widget returns a server error, inspect:

```bash
sudo grep -a -iE 'cisco_wlc|widget|SQLSTATE|exception' \
  /opt/librenms/logs/librenms.log | tail -n 100
```
