# اجرای واقعی راستا روی سرور

این بسته Laravel را با PHP 8.4 و Apache اجرا می‌کند و داده‌ها را در MySQL 8.4 نگه می‌دارد. GitHub Pages تنها فایل ثابت ارائه می‌کند و این برنامه را اجرا نمی‌کند. برای این بسته یک سرور Linux با Docker Engine، Docker Compose، فضای ماندگار و یک دامنه یا تونل HTTPS لازم است. نصب مستقیم روی Cloudflare Workers نیز با این برنامه PHP/MySQL سازگار نیست؛ Cloudflare می‌تواند HTTPS و تونل اتصال به سرور را فراهم کند.

## تنظیم اولیه

در ریشه checkout فعلی کار کنید. هر task ابری محیط جداگانه دارد و برای نصب نیازی به Git worktree نیست.

```bash
cp deploy/.env.production.example deploy/.env.production
chmod 600 deploy/.env.production
```

فایل خصوصی `deploy/.env.production` را تنظیم کنید:

- `APP_URL`: نشانی نهایی HTTPS، بدون مسیر اضافه.
- `APP_KEY`: یک کلید ثابت به شکل `base64:` به‌علاوه ۳۲ بایت تصادفی در قالب base64. آن را با ابزار مدیریت رمز یا `openssl rand -base64 32` بسازید و در فایل خصوصی ذخیره کنید. بعد از ساخت حساب‌ها کلید را عوض نکنید.
- `DB_PASSWORD` و `MYSQL_ROOT_PASSWORD`: دو گذرواژه تصادفی متفاوت. مقادیر دارای `$` را در فایل env داخل تک‌کوتیشن قرار دهید.
- `TRUSTED_PROXIES`: آدرس IP یا CIDR پراکسی مورد اعتماد که درخواست را به Apache می‌رساند. مقدارها با ویرگول جدا می‌شوند و `bootstrap/app.php` آن‌ها را می‌خواند. در حالت پیش‌فرض، هیچ پراکسی مورد اعتماد نیست.

فایل واقعی env در Git و build image قرار نمی‌گیرد. کلید و گذرواژه‌ها باید در secret manager میزبان نیز پشتیبان داشته باشند. مقدارها را در چت یا خروجی عمومی ننویسید.

```bash
docker compose --env-file deploy/.env.production -f docker-compose.production.yml config --quiet
docker compose --env-file deploy/.env.production -f docker-compose.production.yml up -d --build
docker compose --env-file deploy/.env.production -f docker-compose.production.yml ps
curl --fail http://127.0.0.1:8080/up
```

دارایی‌های رابط با `npm ci` و بسته‌های PHP با فایل قفل و `composer install --no-dev` ساخته می‌شوند. هیچ فایل env، حساب نمایشی، دیتابیس توسعه یا فایل آپلودشده وارد image نمی‌شود. در اولین شروع، migration اجرا می‌شود؛ seed اجرا نمی‌شود. حساب خودتان را در صفحه ثبت‌نام بسازید. شروع سرویس به آماده‌شدن MySQL وابسته است؛ خطای migration شروع Apache را متوقف می‌کند.

## HTTPS و پراکسی

پورت برنامه فقط روی `127.0.0.1:8080` سرور باز است و MySQL پورت عمومی ندارد. پراکسی HTTPS میزبان، مانند Caddy یا Nginx، یا یک Cloudflare Tunnel معتبر باید به همین نشانی متصل شود. پراکسی باید `Host` اصلی را حفظ کند و `X-Forwarded-Proto: https` را خودش تعیین کند؛ header دریافتی از کاربر را بدون پاک‌سازی عبور ندهد. آدرس واقعی پراکسی از نگاه کانتینر معمولاً آدرس gateway شبکه Docker است؛ آن را در `TRUSTED_PROXIES` قرار دهید. از اعتماد عمومی به همه آدرس‌ها خودداری کنید و origin را از اینترنت مستقیم در دسترس نگذارید.

برای نمونه Caddy روی میزبان، فایل تنظیم آن می‌تواند شامل این بخش باشد:

```caddyfile
your-domain.example {
    reverse_proxy 127.0.0.1:8080
}
```

DNS دامنه باید به سرور برسد و پورت‌های ۸۰ و ۴۴۳ برای صدور گواهی و HTTPS در دسترس باشند. برای Cloudflare Tunnel از مسیر رسمی سرویس استفاده کنید و token تونل را در secret manager نگه دارید. یک لینک موقت tunnel به روشن‌ماندن همین ماشین وابسته است و میزبانی دائمی محسوب نمی‌شود.

بعد از اتصال HTTPS، ثبت‌نام، ورود، ساخت پروژه و تسک، آپلود و دانلود یک فایل خصوصی و خروج/ورود مجدد را از نشانی عمومی بررسی کنید. آدرس فرم‌ها و redirectها باید HTTPS باشند. `APP_DEBUG=false` و cookie امن در Compose اجباری‌اند. مسیر `/up` تنها پاسخ‌گویی Laravel را بررسی می‌کند؛ آزمایش ذخیره داده برای اطمینان از دیتابیس و مجوزهای فایل همچنان لازم است.

## نگهداری داده و به‌روزرسانی

دو volume مجزا، دیتابیس و پوشه `storage` را نگه می‌دارند. پیوست‌ها خصوصی‌اند و نباید با `storage:link` عمومی شوند. این تنظیم برای یک instance برنامه است؛ افزایش تعداد instanceها به نشست و cache مشترک و ذخیره‌سازی مشترک پیوست‌ها نیاز دارد. صف فعلی `sync` است و worker جداگانه لازم نیست.

پیش از به‌روزرسانی از MySQL و volume فایل‌ها روی مقصدی مستقل پشتیبان بگیرید. نمونه خروجی SQL خصوصی:

```bash
mkdir -p deploy/backups
chmod 700 deploy/backups
umask 077
docker compose --env-file deploy/.env.production -f docker-compose.production.yml exec -T database sh -c 'MYSQL_PWD="$MYSQL_PASSWORD" mysqldump --user="$MYSQL_USER" --single-transaction --no-tablespaces "$MYSQL_DATABASE"' > deploy/backups/rasta.sql
```

فایل dump شامل داده کاربران است؛ در Git یا download عمومی قرار نگیرد. برای آپلودها snapshot امن از volume `rasta-production_uploads-and-sessions` بگیرید و بازیابی هر دو نسخه را روی محیط جداگانه آزمایش کنید. حذف volumeها با `docker compose down -v` تمام داده‌ها را پاک می‌کند؛ برای توقف عادی از `stop` یا `down` بدون `-v` استفاده کنید.

بعد از پشتیبان‌گیری، کد جدید را دریافت و همان دستور `up -d --build` را اجرا کنید. migrationها در شروع با `--force` اجرا می‌شوند. در انتشار چند instance، اجرای migration را به یک release job منتقل و `RUN_MIGRATIONS=false` کنید. گزارش‌ها با دستور زیر در دسترس‌اند؛ خروجی را پیش از اشتراک‌گذاری از نظر داده خصوصی بررسی کنید:

```bash
docker compose --env-file deploy/.env.production -f docker-compose.production.yml logs --tail=100 app
```
