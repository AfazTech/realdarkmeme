# Test report — Laravel rewrite

## تست های قابل اجرای بدون vendor در محیط ساخت
- PHP `php -l` روی تمام فایل های PHP پروژه
- JSON: `composer.json`, `config/render_defaults.json`
- Python: `engine/*.py` syntax via `ast.parse`
- JavaScript: `node --check public/assets/studio.js`
- بررسی ساختار و فایل های ضروری

## تست های PHP/Laravel که در سورس قرار داده شده اند
- Auth + login failure/success
- Validation شناسه عددی کانال
- Remake از فایل اصلی همراه Queue
- Approval یکتا برای هر نسخه و صف ارسال
- فیلتر OCR فارسی

`composer install`، `php artisan test`، تست MadelineProto و تست واقعی ارسال Neili در این محیط اجرا نشده اند. توکن ربات و اکانت تلگرام در محیط ساخت وجود ندارند. قبل از Production حتما تست عملی زیر انجام شود:

1. نصب Composer و وابستگی ها + migrations.
2. اتصال MadelineProto و دریافت یک پست جدید از کانال تست.
3. اطمینان از OCR فارسی و ویدیوی خروجی 1080×1920.
4. Remake و مقایسه نسخه 1 و 2.
5. Approve و ارسال به کانال خصوصی تست؛ مشاهده message_id و تکرار نشدن ارسال.
6. قطع اتصال حین آپلود؛ بررسی وضعیت `uncertain` و رفتار Retry دستی.
7. بررسی مجوزهای Session، تنظیمات HTTPS و بوت سرویس ها.

## v2.2 Telegram UI regression tests

- `tests/Feature/TelegramPanelTest.php`: native panel page, code-entry step, phone submission over CLI bridge (all with a mocked bridge)
- PHP lint checked on the changed sources
- Live Telegram login remains unverified until connected on the deployment server
