# LibreNMS alerting

The plugin exposes a Nagios-compatible LibreNMS Service. Native LibreNMS Alert Rules, Operations, Transports and Templates then handle notifications and recovery messages.

## 1. Verify the service check

Run the check manually before creating an alert:

```bash
sudo -u librenms -H \
  /usr/lib/nagios/plugins/check_cisco_wlc_ap_monitor.php \
  -H WLC_HOSTNAME_OR_IP \
  --device-id LIBRENMS_DEVICE_ID
```

Healthy result:

```text
OK - all monitored APs are online on 192.0.2.10 | down=0
```

Outage result:

```text
CRITICAL - 1 AP(s) down on 192.0.2.10: AP-FLOOR-2 | down=1
```

Ignored and retired APs are excluded from the critical count.

## 2. Add the LibreNMS Service

Open the Cisco WLC device in LibreNMS and add a service with:

- **Service type:** `cisco_wlc_ap_monitor.php`
- **Description:** `Cisco WLC Access Point Monitor`
- **Parameters:** `--device-id <LIBRENMS_DEVICE_ID>`
- **IP/Hostname:** use the WLC hostname or IP already assigned to the device

LibreNMS normally adds `-H <hostname>` automatically. The check accepts that argument.

After adding the service, force a service poll:

```bash
cd /opt/librenms
sudo -u librenms -H ./check-services.php -d
```

Confirm the database state:

```bash
sudo mysql librenms -e "
SELECT service_id,device_id,service_type,service_status,service_message
FROM services
WHERE service_type LIKE 'cisco_wlc_ap_monitor%';
"
```

Service status values used by the check are:

- `0` — OK
- `2` — Critical
- `3` — Unknown, normally caused by configuration or execution problems

## 3. Create the Alert Rule

Create a rule named **Cisco WLC AP Monitor Critical** with:

```text
services.service_status = 2
AND services.service_type = "cisco_wlc_ap_monitor.php"
```

Recommended settings:

- **Severity:** Critical
- **Recovery alerts:** Enabled
- **Mute alerts:** Disabled
- **Delay:** 0–300 seconds, depending on how quickly you want notification
- **Max alerts:** according to your normal alert policy

For multiple WLCs, the rule can remain global. To restrict it, add a device or device-group condition.

## 4. Configure an Alert Transport

Choose any LibreNMS transport already in use, such as:

- Email
- Microsoft Teams
- Slack
- PagerDuty
- Webhook
- Telegram

Test the transport before associating it with the rule.

## 5. Create the Alert Operation

A safe first operation is:

- **Steps from:** `1`
- **Steps to:** `1`
- **Start:** `0`
- **Step duration:** `60`
- **Transport:** your tested transport

This sends once when the AP outage is detected. Configure repeated steps only when repeated notifications are intentional.

## 6. Create the Alert Template

An example Blade template is included in:

```text
examples/alert-template.blade.php
```

The most useful field is the service message, because it contains the exact AP name or names that are down.

Example template:

```blade
@if ($alert->state == 0)
✅ RECOVERED: Cisco WLC AP monitoring
@else
🚨 CRITICAL: Cisco access point offline
@endif

Device: {{ $alert->hostname }}
Rule: {{ $alert->name }}
Severity: {{ $alert->severity }}

@foreach ($alert->faults as $key => $value)
Service: {{ $value['service_desc'] ?? 'Cisco WLC AP Monitor' }}
Status: {{ $value['service_status'] ?? 'unknown' }}
Message: {{ $value['service_message'] ?? 'No service message available' }}
@endforeach
```

Associate the template and operation with the alert rule.

## 7. Test outage and recovery

A safe test method is to use an AP that can be intentionally shut down or disconnected.

1. Run the plugin poll.
2. Run the service poll.
3. Confirm `service_status = 2` and that the AP name appears in `service_message`.
4. Confirm the alert appears in LibreNMS Alerts.
5. Restore the AP.
6. Run the plugin and service polls again.
7. Confirm the alert recovers and a recovery notification is sent.

Manual commands:

```bash
cd /opt/librenms
sudo -u librenms -H php lnms cisco-wlc-ap:poll --no-interaction
sudo -u librenms -H ./check-services.php -d
```

## Troubleshooting alerts

If the management page shows an AP as down but no alert is generated:

- Confirm the service exists on the correct WLC device.
- Confirm the service type is exactly `cisco_wlc_ap_monitor.php`.
- Confirm the parameter contains the correct `--device-id`.
- Run the check manually and inspect its exit code.
- Confirm the Alert Rule matches `services.service_status = 2`.
- Confirm an Alert Operation and Transport are attached to the rule.
- Confirm recovery notifications are enabled when testing restoration.
