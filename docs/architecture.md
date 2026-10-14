# معماری راستا

این طرح پیش از شروع پیاده‌سازی در گفتگو ارائه شد. انتخاب پایه: یک برنامه یکپارچه Laravel با صفحه Blade و تعامل‌های کوچک Alpine، بدون SPA framework و بدون سیستم پیچیده وضعیت یا اولویت.

## مدل‌ها و رابطه‌ها

| موجودیت | رابطه و داده اصلی |
|---|---|
| users | حساب و گذرواژه هش‌شده |
| projects | نام، توضیح، سازنده، رنگ |
| project_members | رابطه چندبه‌چند کاربر/پروژه با owner/member/viewer |
| goals | یک هدف با project_id یکتا، معیار موفقیت و بازه زمانی |
| product_stages | مراحل پروژه، شرح، تاریخ و position |
| member_stages | مراحل اختصاصی یک عضو پروژه، مستقل از مراحل محصول |
| tasks | پروژه، مرحله محصول، مرحله تیمی اختیاری، مسئول اصلی، عنوان، شرح، تاریخ‌ها، completed_at |
| task_members | اعضای مرتبط با هر تسک |
| subtasks | تسک والد، عنوان، ترتیب و تکمیل |
| labels / task_labels | برچسب شخصی یا پروژه‌ای با رنگ و شرح |
| comments | متن، نویسنده و تسک |
| attachments | نام اصلی، نام تصادفی خصوصی، نوع، حجم، آپلودکننده و زمان |
| notices | اعلان اختصاصی مخاطب برای واگذاری، ویرایش و نظر |

همه حذف‌های فرزند با FK کنترل می‌شوند. مرحله دارای تسک قابل حذف نیست؛ مرحله تیمی حذف‌شده از تسک جدا می‌شود. معیار پیشرفت از نسبت تسک‌های تکمیل‌شده محاسبه می‌شود. اعلان مهلت‌های نزدیک از تسک‌های باز محاسبه و در پنل اعلان نمایش داده می‌شود.

## مسیرها

صفحات: `/login`, `/register`, `/dashboard`, `/projects`, `/projects/{project}`, `/tasks`, `/tasks/{task}`, `/labels`, `/settings`.

همه مسیرهای `/api` زیر middlewareهای web، auth و throttle هستند و CSRF دارند:

| متد | مسیر | کارکرد |
|---|---|---|
| GET | /api/workspace | پروژه‌ها و تسک‌های قابل دسترس، برچسب‌ها و اعلان‌ها |
| POST | /api/projects | ثبت اتمیک پروژه، هدف و مراحل wizard |
| PUT | /api/projects/{project}/goal | ویرایش هدف، فقط مالک |
| POST | /api/projects/{project}/members | افزودن یا تغییر نقش کاربر ثبت‌نام‌شده، فقط مالک |
| POST | /api/projects/{project}/stages | مرحله محصول یا عضو، پس از هدف |
| PUT/DELETE | /api/projects/{project}/stages/{kind}/{id} | ویرایش/حذف مرحله؛ kind برابر product/member |
| PUT | /api/projects/{project}/stages/{kind}/order | ثبت ترتیب کامل، با کنترل تعلق همه شناسه‌ها |
| POST | /api/tasks | ساخت تسک و رابطه‌هایش در تراکنش |
| GET/PUT/DELETE | /api/tasks/{task} | خواندن/ویرایش/حذف با کنترل پروژه |
| PATCH | /api/tasks/{task}/complete | تکمیل یا بازکردن |
| POST | /api/labels | برچسب جدید |
| PUT/DELETE | /api/labels/{label} | مدیریت برچسب قابل دسترس |
| POST | /api/tasks/{task}/comments | افزودن نظر |
| PUT/DELETE | /api/comments/{comment} | فقط نویسنده نظر |
| POST | /api/tasks/{task}/attachments | فایل خصوصی با اعتبارسنجی |
| GET | /attachments/{attachment} | دانلود مجاز |
| DELETE | /api/attachments/{attachment} | حذف توسط آپلودکننده یا مالک |
| POST | /api/notices/read | خوانده‌شدن اعلان‌های خود کاربر |
| PUT | /api/settings | نام و ایمیل حساب |

## ساختار پوشه

- `app/Models`: مدل‌ها و رابطه‌های Eloquent
- `app/Services/ProjectAccess.php`: کنترل مشترک نقش و عضویت
- `app/Http/Controllers`: کنترل احراز هویت و عملیات workspace
- `database/migrations`: ساختار و محدودیت‌های دیتابیس
- `database/seeders`: داده فارسی نمونه، فقط توسعه
- `lang/fa`: پیام‌های اعتبارسنجی
- `resources/views/workspace.blade.php`: پوسته راست‌به‌چپ
- `resources/views/partials`: صفحات و مودال‌ها؛ کارت پروژه/تسک قابل استفاده مجدد
- `resources/views/components/date-picker.blade.php`: انتخاب تقویم جلالی با Intl، ذخیره ISO
- `resources/js/app.js`: وضعیت رابط، درخواست‌های CSRF، تاریخ و مرتب‌سازی
- `resources/css/app.css`: توکن‌ها، Tailwind و سبک اجزای مشترک
- `tests/Feature`: آزمون قرارداد و کنترل دسترسی
- `tests/Browser`: تعامل واقعی در Chromium
- `scripts`: نصب، اجرا و نسخه پشتیبان محیط ابری

## اجزا و جریان UX

Button، Input، Textarea، Select، Dropdown، Modal، Avatar، Badge، Label، Card، Checkbox، DatePicker، Tooltip (عنوان بومی)، EmptyState، ConfirmDialog و Toast با کلاس‌های مشترک و partialها ساخته شده‌اند. مودال از وسط باز می‌شود، فوکوس را داخل نگه می‌دارد، با Escape بسته می‌شود و تغییر ذخیره‌نشده نیاز به تأیید دارد. هیچ اولویت، sprint، Gantt یا سیستم وضعیت اضافی وجود ندارد.

پروژه جدید: نام و شرح ← هدف و معیار موفقیت و تاریخ ← مراحل قابل ترتیب. ثبت نهایی به‌صورت تراکنش است؛ گام سوم تنها پس از کامل‌شدن هدف فعال می‌شود. تسک: عنوان ← شرح ← زیرتسک ← مرحله ← مسئول و همراهان ← برچسب ← تاریخ‌ها ← فایل ← نظر. مرحله جدید از داخل مودال ساخته و همان‌جا انتخاب می‌شود. درخواست‌های ذخیره دکمه را موقتاً غیرفعال می‌کنند و خطاهای فارسی نزدیک فیلد مربوط نمایش داده می‌شوند.

خانه: خلاصه فعالیت، پروژه‌ها، قدم‌های بعدی. پروژه: کارت هدف، برد مراحل و مسیر مستقل اعضا. تسک‌های من: مسئولیت اصلی یا همراهی، فیلتر باز/بسته، نمایش برد یا فهرست. موبایل: سایدبار تاشو و تبدیل برد به فهرست مرحله‌ها.

محدوده نسخه حاضر: حساب و پروژه‌های کوچک تیمی. عضویت با ایمیل کاربر موجود انجام می‌شود؛ دعوت ایمیلی و بازیابی گذرواژه با ایمیل پیاده‌سازی نشده‌اند. bootstrap فعلی کل workspace قابل دسترس را بارگذاری می‌کند؛ برای workspaceهای بزرگ، صفحه‌بندی سمت سرور باید اضافه شود. توسعه این موارد مستقل از آماده‌سازی پیش‌نمایش است.
