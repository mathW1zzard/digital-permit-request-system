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
log "backup: $(basename "$FILE") ($(wc -c < "$FILE") bytes, sha256 $(sha256sum "$FILE" | cut -c1-12))"
echo "$FILE"
