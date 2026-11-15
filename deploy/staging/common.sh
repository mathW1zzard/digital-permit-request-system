# Shared settings for the Week 16 staging scripts (Git Bash on Windows). Source it; do not run it.
set -euo pipefail

REPO="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd -W)"          # C:/.../project32-permit
STAGING="${STAGING:-$(cd "$REPO/.." && pwd -W)/permit-staging}"       # outside the repository
SHARED="$STAGING/shared"
LARAGON="${LARAGON:-C:/laragon/bin}"
PHP_HOME="${PHP_HOME:-$LARAGON/php/php-8.4.12-nts-Win32-vs17-x64}"
NGINX_HOME="${NGINX_HOME:-$LARAGON/nginx/nginx-1.28.2}"
MYSQL_HOME="${MYSQL_HOME:-$LARAGON/mysql/mysql-8.4.3-winx64}"
PHP="$PHP_HOME/php.exe"
STAGING_URL="https://localhost:8444"
WORKER_PORTS="9101 9102 9103 9104"
MYSQL_PORT=3307

log() { printf '%s %s\n' "$(date -u +%Y-%m-%dT%H:%M:%SZ)" "$*" | tee -a "$SHARED/logs/ops.log"; }

# Read one value from the shared .env without printing it.
env_value() { grep -m1 "^$1=" "$SHARED/.env" | cut -d= -f2- | sed -e 's/^"//' -e 's/"$//'; }

# A Windows directory junction (works without administrator rights, unlike symlinks).
junction() { # junction <link> <target>
    local link target
    link="$(cygpath -w "$1")"; target="$(cygpath -w "$2")"
    [ -e "$1" ] && cmd //c rmdir "$link" >/dev/null
    cmd //c mklink //J "$link" "$target" >/dev/null
}

mysql_cli() { "$MYSQL_HOME/bin/mysql.exe" --protocol=TCP -h 127.0.0.1 -P "$MYSQL_PORT" "$@"; }

# Readiness (Week 16 releases), or liveness (/up) for older releases that have no /api/health.
health() {
    local code
    code="$(curl -s --ssl-no-revoke --cacert "$REPO/certs/dev-ca.pem" -o /dev/null -w '%{http_code}' "$STAGING_URL/api/health")"
    [ "$code" = 404 ] && code="$(curl -s --ssl-no-revoke --cacert "$REPO/certs/dev-ca.pem" -o /dev/null -w '%{http_code}' "$STAGING_URL/up")"
    echo "$code"
}
