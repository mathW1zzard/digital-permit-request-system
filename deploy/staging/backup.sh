#!/usr/bin/env bash
# Back up the staging database to shared/backups/<time>-<label>.sql (consistent snapshot, no locks).
#   backup.sh [label]
# Restore: rollback.sh --restore <file>
source "$(dirname "$0")/common.sh"

LABEL="${1:-manual}"
FILE="$SHARED/backups/$(date +%Y%m%d-%H%M%S)-$LABEL.sql"
MYSQL_PWD="$(cat "$SHARED/secrets/mysql-root.txt")" \
    "$MYSQL_HOME/bin/mysqldump.exe" --protocol=TCP -h 127.0.0.1 -P "$MYSQL_PORT" -u root \
    --single-transaction --routines --no-tablespaces --databases permit_staging > "$FILE"
SUM="$(sha256sum "$FILE" | cut -c1-64)"
log "backup: $(basename "$FILE") ($(wc -c < "$FILE") bytes, sha256 ${SUM:0:12})"

# Week 17: a second copy off this machine's disk. Set OFFSITE_DIR to a folder on another device,
# for example a USB drive or a synced cloud folder. The copy is checked against the original.
if [ -n "${OFFSITE_DIR:-}" ]; then
    mkdir -p "$OFFSITE_DIR"
    cp "$FILE" "$OFFSITE_DIR/"
    COPY_SUM="$(sha256sum "$OFFSITE_DIR/$(basename "$FILE")" | cut -c1-64)"
    if [ "$COPY_SUM" = "$SUM" ]; then
        log "backup: off-machine copy verified in $OFFSITE_DIR"
    else
        log "backup: OFF-MACHINE COPY DOES NOT MATCH ($OFFSITE_DIR)"; exit 1
    fi
fi
echo "$FILE"
