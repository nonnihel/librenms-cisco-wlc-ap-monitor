#!/usr/bin/env bash
set -Eeuo pipefail
LIBRENMS_BASE="${LIBRENMS_BASE:-/opt/librenms}"
STAMP="$(date +%Y%m%d-%H%M%S)"
DEST="${1:-/var/backups/librenms-cisco-wlc-ap-monitor-$STAMP}"
mkdir -p "$DEST"

mysqldump --single-transaction librenms cisco_wlc_ap_monitor > "$DEST/cisco_wlc_ap_monitor.sql"
cp -a "$LIBRENMS_BASE/local-plugins/librenms-cisco-wlc-ap-monitor" "$DEST/plugin"
cp -a "$LIBRENMS_BASE/composer.plugins.json" "$DEST/" 2>/dev/null || true
cp -a /etc/cron.d/librenms-cisco-wlc-ap-monitor "$DEST/" 2>/dev/null || true
cp -a /usr/lib/nagios/plugins/check_cisco_wlc_ap_monitor* "$DEST/" 2>/dev/null || true
mysql -N -B librenms -e "SELECT service_id,device_id,service_type,service_desc,service_param,service_status FROM services WHERE service_type LIKE 'cisco_wlc_ap_monitor%';" > "$DEST/services.tsv" || true

tar -C "$(dirname "$DEST")" -czf "$DEST.tar.gz" "$(basename "$DEST")"
echo "$DEST.tar.gz"
