#!/usr/bin/env bash
# One-time setup of the Week 16 staging environment:
#   - folders: releases/, shared/{logs,run,backups,storage,nginx,mysql-data}
#   - a private MySQL 8.4 instance on 127.0.0.1:3307 with its own data folder
#     (the Laragon MySQL on 3306 is not touched)
#   - database permit_staging and user permit_app (rights on that database only)
#   - shared/.env with freshly generated secrets; nothing secret is printed
# Run again safely: steps that are already done are skipped.
source "$(dirname "$0")/common.sh"

mkdir -p "$STAGING/releases" "$SHARED"/{logs,run,backups,secrets} \
         "$SHARED"/storage/{app/public,framework/{cache/data,sessions,views,testing},logs} \
         "$SHARED"/nginx/{logs,temp}
log "setup: staging folder $STAGING"

rand() { "$PHP" -r "echo rtrim(strtr(base64_encode(random_bytes($1)), '+/', 'AZ'), '=');"; }

# --- MySQL -------------------------------------------------------------------------------------
cat > "$SHARED/my.ini" <<EOF
[mysqld]
basedir="$MYSQL_HOME"
datadir="$SHARED/mysql-data"
port=$MYSQL_PORT
bind-address=127.0.0.1
mysqlx=OFF
log-error="$SHARED/logs/mysql-error.log"
max_connections=60
innodb_buffer_pool_size=256M
EOF

if [ ! -d "$SHARED/mysql-data/mysql" ]; then
    log "setup: initialising MySQL data folder"
    "$MYSQL_HOME/bin/mysqld.exe" --defaults-file="$SHARED/my.ini" --initialize-insecure
fi

started_here=0
if ! mysql_cli -u root -e "select 1" >/dev/null 2>&1 && [ ! -f "$SHARED/secrets/mysql-root.txt" ]; then
    "$MYSQL_HOME/bin/mysqld.exe" --defaults-file="$SHARED/my.ini" &
    started_here=1
    for _ in $(seq 1 30); do mysql_cli -u root -e "select 1" >/dev/null 2>&1 && break; sleep 1; done
fi

if [ ! -f "$SHARED/secrets/mysql-root.txt" ]; then
    ROOT_PW="$(rand 18)"; APP_PW="$(rand 18)"
    mysql_cli -u root <<SQL
ALTER USER 'root'@'localhost' IDENTIFIED BY '$ROOT_PW';
CREATE DATABASE IF NOT EXISTS permit_staging CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'permit_app'@'127.0.0.1' IDENTIFIED BY '$APP_PW';
CREATE USER IF NOT EXISTS 'permit_app'@'localhost' IDENTIFIED BY '$APP_PW';
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, INDEX, DROP, REFERENCES, LOCK TABLES
      ON permit_staging.* TO 'permit_app'@'127.0.0.1', 'permit_app'@'localhost';
SQL
    printf '%s' "$ROOT_PW" > "$SHARED/secrets/mysql-root.txt"
    printf '%s' "$APP_PW" > "$SHARED/secrets/mysql-app.txt"
    log "setup: MySQL users created (passwords in shared/secrets, not printed)"
fi

if [ "$started_here" = 1 ]; then
    "$MYSQL_HOME/bin/mysqladmin.exe" --protocol=TCP -h 127.0.0.1 -P "$MYSQL_PORT" -u root \
        --password="$(cat "$SHARED/secrets/mysql-root.txt")" shutdown 2>/dev/null || true
fi

# --- shared .env -------------------------------------------------------------------------------
if [ ! -f "$SHARED/.env" ]; then
    dev() { grep -m1 "^$1=" "$REPO/backend/.env" | cut -d= -f2-; }
    APP_KEY="$(cd "$REPO/backend" && "$PHP" artisan key:generate --show)"
    sed -e "s|^APP_KEY=.*|APP_KEY=$APP_KEY|" \
        -e "s|^PERMIT_LOG_PATH=.*|PERMIT_LOG_PATH=\"$SHARED/storage/logs/permit.jsonl\"|" \
        -e "s|^DB_PASSWORD=.*|DB_PASSWORD=$(cat "$SHARED/secrets/mysql-app.txt")|" \
        -e "s|^DEMO_PASSWORD=.*|DEMO_PASSWORD=Staging-$(rand 9)|" \
        -e "s|^GATEWAY_CA_FILE=.*|GATEWAY_CA_FILE=\"$REPO/certs/dev-ca.pem\"|" \
        -e "s|^GATEWAY_API_KEY=.*|GATEWAY_API_KEY=$(dev GATEWAY_API_KEY)|" \
        -e "s|^GATEWAY_PAYLOAD_KEY_ID=.*|GATEWAY_PAYLOAD_KEY_ID=$(dev GATEWAY_PAYLOAD_KEY_ID)|" \
        -e "s|^GATEWAY_PAYLOAD_KEY=.*|GATEWAY_PAYLOAD_KEY=$(dev GATEWAY_PAYLOAD_KEY)|" \
        "$REPO/deploy/staging/.env.staging.example" | grep -v '^#' > "$SHARED/.env"
    log "setup: shared/.env written with new APP_KEY, database and demo passwords (not printed)"
fi

# --- nginx and PHP configuration ----------------------------------------------------------------
sed -e "s|@STAGING@|$STAGING|g" -e "s|@REPO@|$REPO|g" -e "s|@NGINX_HOME@|$NGINX_HOME|g" \
    "$REPO/deploy/staging/nginx.conf.template" > "$SHARED/nginx/nginx.conf"
cat "$PHP_HOME/php.ini" "$REPO/deploy/staging/php-staging.overrides.ini" > "$SHARED/php-staging.ini"
log "setup: nginx.conf and php-staging.ini generated"
echo "Staging folder ready: $STAGING"
