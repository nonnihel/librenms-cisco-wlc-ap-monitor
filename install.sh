#!/usr/bin/env bash
set -Eeuo pipefail

LIBRENMS_BASE="${LIBRENMS_BASE:-/opt/librenms}"
LIBRENMS_USER="${LIBRENMS_USER:-librenms}"
PACKAGE_NAME="averna/librenms-cisco-wlc-ap-monitor"
PLUGIN_DIR="$LIBRENMS_BASE/local-plugins/librenms-cisco-wlc-ap-monitor"
SOURCE_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
CRON_FILE="/etc/cron.d/librenms-cisco-wlc-ap-monitor"

if [[ $EUID -ne 0 ]]; then
  echo "Run this installer with sudo: sudo ./install.sh" >&2
  exit 1
fi

for required in "$LIBRENMS_BASE/lnms" "$LIBRENMS_BASE/scripts/composer_wrapper.php"; do
  [[ -e "$required" ]] || { echo "Missing LibreNMS file: $required" >&2; exit 1; }
done

if [[ "$SOURCE_DIR" != "$PLUGIN_DIR" ]]; then
  install -d -o "$LIBRENMS_USER" -g "$LIBRENMS_USER" "$PLUGIN_DIR"
  rsync -a --delete --exclude='.git' --exclude='*.zip' "$SOURCE_DIR/" "$PLUGIN_DIR/"
fi
chown -R "$LIBRENMS_USER:$LIBRENMS_USER" "$PLUGIN_DIR"

run_lnms() {
  sudo -u "$LIBRENMS_USER" -H bash -lc "cd '$LIBRENMS_BASE' && $*"
}

run_lnms "./scripts/composer_wrapper.php config --global repositories.cisco-wlc-ap-monitor '{\"type\":\"path\",\"url\":\"$PLUGIN_DIR\",\"options\":{\"symlink\":true}}'"

if ! run_lnms "./scripts/composer_wrapper.php show '$PACKAGE_NAME'" >/dev/null 2>&1; then
  run_lnms "./lnms plugin:add '$PACKAGE_NAME' '@dev'"
fi

run_lnms "./lnms migrate --force"
run_lnms "php artisan package:discover"
run_lnms "php artisan optimize:clear"

NAGIOS_DIR="$(run_lnms "./lnms config:get nagios_plugins" 2>/dev/null | tail -n1 | tr -d '\r')"
NAGIOS_DIR="${NAGIOS_DIR:-/usr/lib/nagios/plugins}"
install -d -o root -g root -m 0755 "$NAGIOS_DIR"
install -o root -g root -m 0755 "$PLUGIN_DIR/scripts/check_cisco_wlc_ap_monitor.php" "$NAGIOS_DIR/check_cisco_wlc_ap_monitor.php"
ln -sfn "$NAGIOS_DIR/check_cisco_wlc_ap_monitor.php" "$NAGIOS_DIR/check_cisco_wlc_ap_monitor"

cat > "$CRON_FILE" <<CRON
*/5 * * * * $LIBRENMS_USER cd $LIBRENMS_BASE && php lnms cisco-wlc-ap:poll --no-interaction >> logs/cisco-wlc-ap-monitor.log 2>&1
CRON
chown root:root "$CRON_FILE"
chmod 0644 "$CRON_FILE"

run_lnms "php lnms cisco-wlc-ap:poll --no-interaction"
"$PLUGIN_DIR/tools/healthcheck.sh" || true

echo
echo "Installation completed."
echo "GUI: /cisco-wlc-ap-monitor"
echo "Widget: /cisco-wlc-ap-monitor/widget"
