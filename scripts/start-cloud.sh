#!/usr/bin/env bash
set -euo pipefail
cd /workspace/test
export PATH=/workspace/.runtime/bin:$PATH
if ! docker inspect rasta-mysql >/dev/null 2>&1; then
  docker run -d --name rasta-mysql --env-file /workspace/.runtime/mysql.env -p 127.0.0.1:3306:3306 -v rasta-mysql-data:/var/lib/mysql mysql:8.4 >/dev/null
else
  docker start rasta-mysql >/dev/null
fi
ready=0
for attempt in $(seq 1 45); do
  if docker exec rasta-mysql sh -c 'MYSQL_PWD="$MYSQL_PASSWORD" mysql -u "$MYSQL_USER" "$MYSQL_DATABASE" -e "SELECT 1"' >/dev/null 2>&1; then ready=1; break; fi
  sleep 1
done
if [ "$ready" != 1 ]; then echo 'MySQL did not become ready.' >&2; exit 1; fi
if ! docker exec rasta-mysql sh -c 'MYSQL_PWD="$MYSQL_PASSWORD" mysql -u "$MYSQL_USER" "$MYSQL_DATABASE" -e "SELECT 1 FROM migrations LIMIT 1"' >/dev/null 2>&1 && [ -s /workspace/.runtime/rasta.sql ]; then
  docker exec -i rasta-mysql sh -c 'MYSQL_PWD="$MYSQL_PASSWORD" mysql -u "$MYSQL_USER" "$MYSQL_DATABASE"' < /workspace/.runtime/rasta.sql
fi
php artisan migrate --force
cd public
exec php -S 0.0.0.0:8000 /workspace/test/vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php
