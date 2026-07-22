#!/usr/bin/env bash
set -Eeuo pipefail
LIBRENMS_BASE="${LIBRENMS_BASE:-/opt/librenms}"
LIBRENMS_USER="${LIBRENMS_USER:-librenms}"
PACKAGE_NAME="averna/librenms-cisco-wlc-ap-monitor"
PLUGIN_DIR="$LIBRENMS_BASE/local-plugins/librenms-cisco-wlc-ap-monitor"

if [[ $EUID -ne 0 ]]; then
  echo "Run with sudo: sudo ./uninstall.sh" >&2
  exit 1
fi

NAGIOS_DIR="$(sudo -u "$LIBRENMS_USER" -H bash -lc "cd '$LIBRENMS_BASE' && ./lnms config:get nagios_plugins" 2>/dev/null | tail -n1 | tr -d '\r')"
NAGIOS_DIR="${NAGIOS_DIR:-/usr/lib/nagios/plugins}"
rm -f /etc/cron.d/librenms-cisco-wlc-ap-monitor
rm -f "$NAGIOS_DIR/check_cisco_wlc_ap_monitor" "$NAGIOS_DIR/check_cisco_wlc_ap_monitor.php"
sudo -u "$LIBRENMS_USER" -H bash -lc "cd '$LIBRENMS_BASE' && ./lnms plugin:remove '$PACKAGE_NAME' || true"
rm -rf "$PLUGIN_DIR"

echo "Plugin files were removed. Database history was retained."
echo "To remove data permanently: mysql librenms -e 'DROP TABLE cisco_wlc_ap_monitor;'"
