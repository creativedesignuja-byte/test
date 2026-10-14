#!/usr/bin/env bash
set -euo pipefail
umask 077
dump=$(mktemp /workspace/.runtime/rasta.sql.XXXXXX)
trap 'rm -f "$dump"' EXIT
docker exec rasta-mysql sh -c 'MYSQL_PWD="$MYSQL_PASSWORD" mysqldump --single-transaction --no-tablespaces --set-gtid-purged=OFF -u "$MYSQL_USER" "$MYSQL_DATABASE"' > "$dump"
test -s "$dump"
mv "$dump" /workspace/.runtime/rasta.sql
