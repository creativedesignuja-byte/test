#!/usr/bin/env bash
set -euo pipefail
# Isolated cloud tasks use the existing checkout; do not create a Git worktree.
cd /workspace/test
runtime=/workspace/.runtime
mkdir -p "$runtime"/{bin,php,debs,root,apt/lists/partial,apt/cache/archives/partial}
cat > "$runtime/apt/sources.list" <<'EOF'
deb [signed-by=/usr/share/keyrings/debian-archive-keyring.gpg] https://deb.debian.org/debian trixie main
deb [signed-by=/usr/share/keyrings/debian-archive-keyring.gpg] https://deb.debian.org/debian-security trixie-security main
EOF
cat > "$runtime/apt.conf" <<'EOF'
Dir::Etc::parts "-";
Dir::Etc::main "-";
Dir::Etc::sourcelist "/workspace/.runtime/apt/sources.list";
Dir::Etc::sourceparts "-";
Dir::State::lists "/workspace/.runtime/apt/lists";
Dir::Cache "/workspace/.runtime/apt/cache";
APT::Get::List-Cleanup "0";
EOF
if [ ! -f "$runtime/root/usr/bin/php8.4" ]; then
 APT_CONFIG="$runtime/apt.conf" /usr/bin/apt-get update
 (cd "$runtime/debs"; APT_CONFIG="$runtime/apt.conf" /usr/bin/apt-get download php8.4-cli php8.4-common php8.4-mbstring php8.4-xml php8.4-curl php8.4-mysql php8.4-sqlite3 php8.4-intl libonig5 libargon2-1)
 for package in "$runtime"/debs/*.deb; do dpkg-deb -x "$package" "$runtime/root"; done
fi
cat > "$runtime/php/php.ini" <<'EOF'
extension_dir=/workspace/.runtime/root/usr/lib/php/20240924
extension=ctype
extension=curl
extension=dom
extension=fileinfo
extension=intl
extension=mbstring
extension=pdo
extension=mysqlnd
extension=pdo_mysql
extension=pdo_sqlite
extension=sqlite3
extension=tokenizer
extension=xml
extension=xmlreader
extension=xmlwriter
extension=simplexml
extension=phar
extension=iconv
extension=posix
upload_max_filesize=10M
post_max_size=12M
memory_limit=512M
date.timezone=Asia/Tehran
EOF
cat > "$runtime/bin/php" <<'EOF'
#!/bin/sh
export LD_LIBRARY_PATH=/workspace/.runtime/root/usr/lib/x86_64-linux-gnu${LD_LIBRARY_PATH:+:$LD_LIBRARY_PATH}
export PHPRC=/workspace/.runtime/php/php.ini
export PHP_INI_SCAN_DIR=/workspace/.runtime/php/none
exec /workspace/.runtime/root/usr/bin/php8.4 -c /workspace/.runtime/php/php.ini "$@"
EOF
cat > "$runtime/bin/composer" <<'EOF'
#!/bin/sh
export COMPOSER_HOME=/workspace/.runtime/composer-home
exec /workspace/.runtime/bin/php /workspace/.runtime/composer.phar "$@"
EOF
chmod +x "$runtime/bin/php" "$runtime/bin/composer"
export PATH="$runtime/bin:$PATH"
if [ ! -f "$runtime/composer.phar" ]; then
 curl --fail --location --retry 2 https://github.com/composer/composer/releases/download/2.8.12/composer.phar -o "$runtime/composer.phar"
fi
printf '%s  %s\n' f446ea719708bb85fcbf4ef18def5d0515f1f9b4d703f6d820c9c1656e10a2f2 "$runtime/composer.phar" | sha256sum --check --status
composer install --no-interaction --prefer-dist
npm ci --cache "$runtime/npm-cache"
npm run build
if [ ! -f "$runtime/mysql.env" ]; then
 python3 - <<'PY'
import pathlib,secrets
p=pathlib.Path('/workspace/.runtime/mysql.env')
p.write_text('MYSQL_ROOT_PASSWORD='+secrets.token_urlsafe(24)+'\nMYSQL_DATABASE=rasta\nMYSQL_USER=rasta\nMYSQL_PASSWORD='+secrets.token_urlsafe(24)+'\n')
p.chmod(0o600)
PY
fi
if [ ! -f .env ]; then
 python3 - <<'PY'
from pathlib import Path
import secrets
v=dict(l.split('=',1) for l in Path('/workspace/.runtime/mysql.env').read_text().splitlines())
p=Path('.env');p.write_text(Path('.env.example').read_text().replace('DB_PASSWORD=','DB_PASSWORD='+v['MYSQL_PASSWORD'])+'\nDEMO_PASSWORD='+secrets.token_urlsafe(20)+'\n');p.chmod(0o600)
PY
 php artisan key:generate
fi
if ! docker inspect rasta-mysql >/dev/null 2>&1; then
 docker run -d --name rasta-mysql --env-file "$runtime/mysql.env" -p 127.0.0.1:3306:3306 -v rasta-mysql-data:/var/lib/mysql mysql:8.4 >/dev/null
else
 docker start rasta-mysql >/dev/null
fi
ready=0
for attempt in $(seq 1 45); do
 if docker exec rasta-mysql sh -c 'MYSQL_PWD="$MYSQL_PASSWORD" mysql -u "$MYSQL_USER" "$MYSQL_DATABASE" -e "SELECT 1"' >/dev/null 2>&1; then ready=1; break; fi
 sleep 1
done
if [ "$ready" != 1 ]; then echo 'MySQL did not become ready.' >&2; exit 1; fi
if ! docker exec rasta-mysql sh -c 'MYSQL_PWD="$MYSQL_PASSWORD" mysql -u "$MYSQL_USER" "$MYSQL_DATABASE" -e "SELECT 1 FROM migrations LIMIT 1"' >/dev/null 2>&1 && [ -s "$runtime/rasta.sql" ]; then
 docker exec -i rasta-mysql sh -c 'MYSQL_PWD="$MYSQL_PASSWORD" mysql -u "$MYSQL_USER" "$MYSQL_DATABASE"' < "$runtime/rasta.sql"
fi
php artisan migrate --force
php artisan db:seed --force
php artisan view:cache
php artisan test --compact
bash scripts/backup-cloud.sh
