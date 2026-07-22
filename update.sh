#!/usr/bin/env bash
set -Eeuo pipefail

LIBRENMS_BASE="${LIBRENMS_BASE:-/opt/librenms}"
LIBRENMS_USER="${LIBRENMS_USER:-librenms}"
PLUGIN_DIR="$LIBRENMS_BASE/local-plugins/librenms-cisco-wlc-ap-monitor"
SOURCE_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

if [[ $EUID -ne 0 ]]; then
  echo "Run with sudo: sudo ./update.sh" >&2
  exit 1
fi

if [[ "$SOURCE_DIR" != "$PLUGIN_DIR" ]]; then
  rsync -a --delete --exclude='.git' --exclude='*.zip' "$SOURCE_DIR/" "$PLUGIN_DIR/"
fi
chown -R "$LIBRENMS_USER:$LIBRENMS_USER" "$PLUGIN_DIR"

sudo -u "$LIBRENMS_USER" -H bash -lc "cd '$LIBRENMS_BASE' && ./lnms migrate --force"
sudo -u "$LIBRENMS_USER" -H bash -lc "cd '$LIBRENMS_BASE' && php artisan package:discover"
sudo -u "$LIBRENMS_USER" -H bash -lc "cd '$LIBRENMS_BASE' && php artisan optimize:clear"

NAGIOS_DIR="$(sudo -u "$LIBRENMS_USER" -H bash -lc "cd '$LIBRENMS_BASE' && ./lnms config:get nagios_plugins" 2>/dev/null | tail -n1 | tr -d '\r')"
NAGIOS_DIR="${NAGIOS_DIR:-/usr/lib/nagios/plugins}"
install -d -o root -g root -m 0755 "$NAGIOS_DIR"
install -o root -g root -m 0755 "$PLUGIN_DIR/scripts/check_cisco_wlc_ap_monitor.php" "$NAGIOS_DIR/check_cisco_wlc_ap_monitor.php"
ln -sfn "$NAGIOS_DIR/check_cisco_wlc_ap_monitor.php" "$NAGIOS_DIR/check_cisco_wlc_ap_monitor"

"$PLUGIN_DIR/tools/ensure-plugin.sh"
"$PLUGIN_DIR/tools/healthcheck.sh"
