#!/usr/bin/env bash
set -Eeuo pipefail
LIBRENMS_BASE="${LIBRENMS_BASE:-/opt/librenms}"
LIBRENMS_USER="${LIBRENMS_USER:-librenms}"
PACKAGE_NAME="averna/librenms-cisco-wlc-ap-monitor"
PLUGIN_DIR="$LIBRENMS_BASE/local-plugins/librenms-cisco-wlc-ap-monitor"

run_lnms() { sudo -u "$LIBRENMS_USER" -H bash -lc "cd '$LIBRENMS_BASE' && $*"; }

if run_lnms "php artisan route:list" 2>/dev/null | grep -q 'cisco-wlc-ap-monitor/widget'; then
  echo "Plugin routes are present."
  exit 0
fi

echo "Plugin routes are missing; restoring package registration..."
run_lnms "./scripts/composer_wrapper.php config --global repositories.cisco-wlc-ap-monitor '{\"type\":\"path\",\"url\":\"$PLUGIN_DIR\",\"options\":{\"symlink\":true}}'"
run_lnms "./lnms plugin:add '$PACKAGE_NAME' '@dev'"
run_lnms "php artisan package:discover"
run_lnms "php artisan optimize:clear"
run_lnms "php artisan route:list" | grep 'cisco-wlc-ap-monitor'
