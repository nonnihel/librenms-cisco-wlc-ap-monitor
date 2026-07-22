#!/usr/bin/env bash
set -u
LIBRENMS_BASE="${LIBRENMS_BASE:-/opt/librenms}"
LIBRENMS_USER="${LIBRENMS_USER:-librenms}"
failed=0

check() { printf '%-42s' "$1"; shift; if "$@" >/dev/null 2>&1; then echo 'OK'; else echo 'FAIL'; failed=1; fi; }

check "Plugin directory" test -d "$LIBRENMS_BASE/local-plugins/librenms-cisco-wlc-ap-monitor"
check "composer.plugins.json registration" grep -q 'averna/librenms-cisco-wlc-ap-monitor' "$LIBRENMS_BASE/composer.plugins.json"
check "Vendor package link" test -e "$LIBRENMS_BASE/vendor/averna/librenms-cisco-wlc-ap-monitor"
check "GUI route" bash -lc "sudo -u '$LIBRENMS_USER' -H php '$LIBRENMS_BASE/artisan' route:list | grep -q 'cisco-wlc-ap-monitor.index'"
check "Widget route" bash -lc "sudo -u '$LIBRENMS_USER' -H php '$LIBRENMS_BASE/artisan' route:list | grep -q 'cisco-wlc-ap-monitor.widget'"
check "Plugin database table" bash -lc "mysql -N librenms -e \"SHOW TABLES LIKE 'cisco_wlc_ap_monitor'\" | grep -q cisco_wlc_ap_monitor"
check "Polling cron" test -f /etc/cron.d/librenms-cisco-wlc-ap-monitor

NAGIOS_DIR="$(sudo -u "$LIBRENMS_USER" -H bash -lc "cd '$LIBRENMS_BASE' && ./lnms config:get nagios_plugins" 2>/dev/null | tail -n1 | tr -d '\r')"
NAGIOS_DIR="${NAGIOS_DIR:-/usr/lib/nagios/plugins}"
check "Nagios service check" test -x "$NAGIOS_DIR/check_cisco_wlc_ap_monitor.php"

exit "$failed"
