#!/usr/bin/env bash
set -Eeuo pipefail
ARCHIVE="${1:-}"
[[ -n "$ARCHIVE" && -f "$ARCHIVE" ]] || { echo "Usage: sudo bash $0 backup.tar.gz" >&2; exit 1; }
TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT
tar -xzf "$ARCHIVE" -C "$TMP"
ROOT="$(find "$TMP" -mindepth 1 -maxdepth 1 -type d | head -n1)"
[[ -f "$ROOT/cisco_wlc_ap_monitor.sql" ]] || { echo "Database dump not found" >&2; exit 1; }
mysql librenms < "$ROOT/cisco_wlc_ap_monitor.sql"
echo "Plugin state restored. Install the matching plugin release and recreate the LibreNMS Service/Alert configuration as documented in docs/MIGRATION.md."
