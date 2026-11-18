> **v2.2 Telegram panel:** The Telegram Account page now uses a CLI authorization bridge, never MadelineProto's automatic web login. Configure `RDM_PHP_CLI` if your PHP-FPM user cannot locate the PHP CLI binary. See `UPGRADE_v2.2_FA.md`.

# RealDarkMeme Studio — Laravel 13

نسخه بازنویسی شده و مستقل RealDarkMeme با طراحی اختصاصی LTR و پنل انگلیسی و مدیریت کامل چرخه دریافت تا انتشار ویدیو.

> **وضعیت:** سورس Laravel و موتور رندر کامل داخل پروژه است. `vendor/` و `composer.lock` در محیط ساخت تولید نشده اند، چون دسترسی به Composer/Packagist در این محیط برقرار نبود. تست Syntax PHP/JavaScript/Python انجام شده؛ تست یکپارچه Laravel/Telegram باید بعد از `composer install` روی سرور انجام شود. **این پروژه را بدون تست، مستقیما جایگزین نسخه Production نکن.**

## امکانات

- Laravel 13 + Blade + CSS اختصاصی + JS ساده؛ بدون Vue/React، بدون Vite/npm و بدون قالب ادمین آماده.
- لاگین مدیریت با Session/CSRF/Throttling، هش امن رمز، تغییر رمز با هر طول غیرخالی.
- اتصال یک حساب تلگرام از مرورگر با MadelineProto و دریافت فقط ویدیوهای پست های جدید از Channel Peer ID های فعال.
- آرشیو ویدیو، وضعیت دانلود/صف/رندر/آماده/خطا، نمایش خروجی و دانلود امن از مسیر خصوصی.
- OCR فارسی فقط فریم اول با Tesseract `fas`؛ OCR و کپشن کانال مبدا با هم ترکیب نمی شوند.
- حفظ موتور Python/FFmpeg اصلی برای بلور 1080x1920، متن کاور فارسی، فونت و ایموجی، واترمارک متحرک، Outro، CRF، FPS، صدا.
- تنظیمات پیش فرض رندر و هشتگ در صفحات مستقل Studio Settings و App Settings؛ تنظیمات خصوصی در Remake؛ تاریخچه نسخه های مستقل.
- تایید ادمین (Approve) و ارسال خروجی همان نسخه با کپشن از طریق **Neili** و ربات تلگرام.
- وضعیت انتشار: `queued`, `sending`, `sent`, `failed`, `uncertain`. نتیجه نامشخص هرگز اتوماتیک دوباره ارسال نمی شود.
- فایل های حساس و MP4 در `storage/app/private` و خارج از `public`.
- Migration اختیاری، فقط خواندنی از نسخه قدیمی PHP/SQLite؛ دیتابیس اصلی و فایل های نسخه قدیمی دست نمی خورند.

## پیش نیازها

- **PHP >= 8.3** به همراه `pdo_sqlite`, `mbstring`, `dom`, `xml`, `curl`, `openssl`, `fileinfo`, `gmp`, `intl`, `zip` (سایر نیازهای Composer در زمان نصب مشخص می شوند).
- Composer 2، Nginx/PHP-FPM برای Production، SQLite برای نصب ساده.
- `ffmpeg` با `libx264`, `drawtext`، `fontconfig`، فونت فارسی Bold (Vazirmatn یا Noto)، فونت ایموجی.
- Python 3 و Pillow با Raqm یا بسته های جایگزین Arabic reshaping.
- Tesseract و زبان فارسی `fas` (آزمون: `tesseract --list-langs`).
- اکانت تلگرام عضو کانال های مبدا و ربات ادمین کانال مقصد با حق ارسال پست.

### نمونه Arch Linux

```bash
sudo pacman -S --needed php php-sqlite composer ffmpeg tesseract tesseract-data-fas \
  python python-pillow fontconfig noto-fonts noto-fonts-emoji
```

نام پکیج ها بسته به ریپازیتوری و نسخه سیستم ممکن است تفاوت کند. با `php -m` مطمئن شو افزونه های لازم فعال اند؛ در Arch بعضی اکستنشن ها باید در `php.ini` فعال شوند.

### Ubuntu/Debian (نام PHP مطابق سرور)

```bash
sudo apt install nginx php-cli php-fpm php-sqlite3 php-mbstring php-curl php-xml php-gmp \
  php-intl php-zip composer ffmpeg python3 python3-venv python3-pip \
  tesseract-ocr tesseract-ocr-fas fontconfig fonts-noto-core fonts-noto-color-emoji
```

## نصب محلی

در این پروژه از `composer create-project` استفاده نکن؛ سورس پروژه از قبل آماده است:

```bash
cd RealDarkMeme-Laravel
cp .env.example .env
composer install
php artisan key:generate
touch database/database.sqlite
php artisan migrate
php artisan rdm:prepare
php artisan rdm:admin admin
php artisan rdm:health
php artisan serve --host=127.0.0.1 --port=8000
```

در مرورگر `http://127.0.0.1:8000/login` را باز کن. رمز ادمین هنگام ساخت در ترمینال، مخفی گرفته می شود و فقط نباید خالی باشد. برای امنیت رمز قوی پیشنهاد می شود.

اگر Python به Pillow مناسب دسترسی ندارد:

```bash
python3 -m venv .venv
.venv/bin/python -m pip install 'Pillow>=10' arabic-reshaper python-bidi
```

سپس `RDM_PYTHON=/absolute/path/to/.venv/bin/python` را در `.env` تنظیم کن.

### اجرای سرویس ها در توسعه

در سه ترمینال جداگانه (پس از ورود اکانت تلگرام):

```bash
php artisan rdm:listen
php artisan queue:work database --queue=render --sleep=3 --tries=1 --timeout=3600
php artisan queue:work database --queue=publish --sleep=3 --tries=1 --timeout=1800
```

فرایند `rdm:listen` باید یک نمونه داشته باشد. تنها ویدیوهای **جدید** کانال هایی که `Peer ID` آن ها فعال است دریافت می شوند. دانلود تاریخچه قدیمی پیش بینی نشده است.

در پنل به صفحه Telegram Account برو؛ API ID/Hash و سپس شماره/کد/2FA را همان جا وارد کن. هنگام اتصال مجدد، Listener قبلی نباید باز باشد. Session در `storage/app/private/telegram` ایجاد می شود.

برای انتقال در Production، ریشه وب سرور فقط پوشه `public` است. دامنه/Socket و TLS فایل `deploy/nginx.conf` را تغییر بده. سه فایل `deploy/rdm-*.service` قالب systemd هستند؛ مسیر پروژه/باینری PHP و کاربر سرویس را تغییر بده. صرفا بعد از بررسی روی محیط تست آن ها را فعال کن.

## انتشار به تلگرام

1. ربات را در کانال مقصد Admin کن و مجوز ارسال پست بده.
2. در «App Settings -> Telegram publishing» توکن، آیدی کانال (`@name` یا `-100...`) و آدرس Bot API را تنظیم کن.
3. اگر آدرس اختصاصی مثل `https://t.afaztech.ir/bot` داری، hostname را در `RDM_ALLOWED_BOT_API_HOSTS` در `.env` مجاز کن؛ تنظیمات پیش فرض `api.telegram.org,t.afaztech.ir` است.
4. از صفحه ویدیو، Preview و کپشن را بررسی کن و دکمه «Approve و انتشار» بزن. ربات از طریق Job مستقل Neili ارسال می کند.
5. انتشار هر `render_id` فقط یک بار تایید می شود. بعد از Remake، نسخه جدید تایید جداگانه می خواهد.

کپشن تلگرام برای فایل ویدیو حداکثر 1024 کاراکتر است. بیش از این مقدار قبل از تایید بلوکه می شود. Bot API عادی برای آپلود فایل محدودیت حجم دارد؛ برای فایل های بزرگ از Bot API Server اختصاصی استفاده کن. در سرور محلی، فایل صرفا در Worker داخلی به مسیر واقعی تبدیل می شود و از طریق وب عمومی سرو نمی شود.

**جلوگیری از ارسال تکراری:** اگر ارتباط پس از آغاز ارسال قطع شود، وضعیت `uncertain` می گیرد. ابتدا کانال را بررسی کن و سپس در صورت لزوم، با تایید احتمال تکرار ارسال مجدد انجام بده. بعد از Crash غیرمنتظره، ارسال های `sending` قدیمی با دستور زیر به `uncertain` منتقل می شوند:

```bash
php artisan rdm:recover-publications
```

این دستور را بعد از راه اندازی Worker و پس از بررسی وضعیت استفاده کن؛ ارسال مجدد خودکار صورت نمی گیرد. برای ویدیوهای با پردازش متوقف شده نیز دستور `php artisan rdm:recover-renders` وضعیت های قدیمی را به `failed` تبدیل می کند تا بتوان بعد از بررسی، Retry کرد.

## انتقال اختیاری داده های نسخه قدیمی

**نسخه قدیمی را حذف یا overwrite نکن.** قبل از انتقال از SQLite، `.env`، ویدیوها و Session بکاپ بگیر. Listener/Worker قبلی را پیش از Import نهایی متوقف کن. یک Laravel تازه و خالی بساز و نصبش را تا `migrate` و `rdm:admin` انجام بده؛ سپس:

```bash
php artisan rdm:import-legacy /absolute/path/to/old/realdarkmeme-web
```

این دستور **Dry-run** است و فقط تعداد رکوردها را نشان می دهد. پس از بررسی:

```bash
php artisan rdm:import-legacy /absolute/path/to/old/realdarkmeme-web --apply
```

برای انتقال اختیاری تنظیمات ربات/توکن به صورت رمزگذاری شده، `--bot` را هم به همین دستور اضافه کن. Import فقط در دیتابیس **خالی** انجام می شود و خودش هرگز پایگاه داده یا فایل های نسخه قدیمی را تغییر نمی دهد. کاربر/رمز ادمین و Session اکانت تلگرام عمدا منتقل نمی شوند. برای Session، سرویس ها را متوقف کن، سپس با بکاپ و مجوزهای مناسب فایل های مربوط به MadelineProto را جداگانه جابجا کن یا از پنل دوباره Login کن.

ویدیوهای قدیمی که در حال پردازش/دانلود بوده اند، برای جلوگیری از اجرای ناخواسته به حالت `failed` وارد می شوند تا با بررسی دستی Retry شوند. ارسال هایی که قبلا `queued/sending` بوده اند به `uncertain` تبدیل می شوند. `sent` حفظ می شود. اگر خروجی قدیمی در دیسک وجود نداشته باشد، رندر به عنوان ناموفق وارد می شود.

## چیدمان سورس

```text
app/Http/Controllers/      Auth, Dashboard, Videos, Settings, Channels, Telegram, Publications
app/Models/                 User, Channel, Video, Render, Publication, Setting, ActivityLog
app/Jobs/                   ProcessVideo, PublishVideo
app/Services/               VideoPipeline (ffmpeg, OCR, Python adapter)
app/Telegram/               VideoEventHandler (MadelineProto)
app/Console/Commands/       installer, healthcheck, listener, migration helper
resources/views/            Blade dashboard, media library, detail, settings, auth
public/assets/             UI styles, interactions; static without npm/Vite
engine/                    original make.py/settings.py + render_job.py
assets/                    original thumbnail.png/outro.png (private seed assets)
database/migrations/        Schema: users, channels, videos, renders, publications, jobs, logs
storage/app/private/        original media, outputs, OCR frames, telegram session
```

## امنیت و عملیات

- `APP_DEBUG=false`, `APP_ENV=production`, `APP_URL` با HTTPS، `SESSION_SECURE_COOKIE=true` برای Production.
- `.env` و `storage/` خارج از `public` و غیرقابل دانلود باشند؛ Session MadelineProto برابر دسترسی به اکانت کاربری است.
- رمزگذاری توکن ربات با `APP_KEY` انجام می شود؛ این کلید را بکاپ بگیر و بدون برنامه مهاجرت تغییر نده.
- برای جلوگیری از SSRF، hostname Bot API باید در لیست مجاز `.env` وجود داشته باشد. هر هاست جدیدی را فقط در صورت اعتماد اضافه کن.
- تنها یک Listener و یک Render Worker توصیه می شود؛ کارهای CPU سنگین ممکن است منابع سیستم را مصرف کنند.
- `queue.retry_after=4200` برای Job های تا 3600 ثانیه تنظیم شده است؛ اگر زمان رندر را تغییر می دهی، این مقدار هم باید بیشتر از Timeout باشد.
- داده های ویدیو به صورت خودکار پاک نمی شوند؛ سیاست retention و بکاپ دیسک با ادمین است.
- `MadelineProto` تحت AGPL است؛ شرایط مجوز را برای استفاده و توزیع بررسی کن.

## تست و محدودیت تحویل

```bash
# پس از composer install
php artisan test
php artisan route:list
php artisan rdm:health
```

اینجا PHP Syntax، JSON، Python AST و JavaScript Syntax بررسی شده اند. به دلیل نبود `composer`/دسترسی شبکه برای دریافت `vendor`، اجرای واقعی `php artisan test` و تست End-to-End تلگرام روی ماشین ساخت امکان پذیر نبود؛ بنابراین وضعیت **آماده نصب و تست روی سرور** است، نه تایید شده برای Cutover Production.


## تغییرات v2.1

راهنمای آپدیت بدون حذف داده ها در `UPGRADE_v2.1_FA.md` قرار دارد. پنل به انگلیسی و LTR منتقل شد و اتصال Telegram با فرم داخلی انجام می شود. Studio Settings از App Settings جدا شده است.

## Telegram authentication queue (v2.3)

Telegram Account is non-blocking in v2.3. While signing in, run a **dedicated** Laravel database queue worker in addition to the listener/render/publish workers:

```bash
php artisan queue:work database --queue=telegram --sleep=1 --tries=1 --timeout=110
```

The Telegram session is not opened by ordinary HTTP page requests. An existing session must be checked through the **Check session status** action with the listener stopped. See `UPGRADE_v2.3_FA.md` for patch instructions and diagnostics. The sample systemd unit is `deploy/rdm-telegram.service`.
