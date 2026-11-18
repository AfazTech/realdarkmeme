# RealDarkMeme Studio v2.1 — راهنمای آپدیت

این آپدیت روی پروژه **Laravel v2.0** اعمال می شود، نه نسخه قدیمی PHP Pure. آپدیت هیچ فایل موجود در storage یا database را حذف یا جایگزین نمی کند و Migration جدیدی ندارد.

## تغییرات

- پنل انگلیسی و چیدمان LTR؛ متن و OCR فارسی ویدیوها تغییر نمی کنند.
- صفحه Telegram Account شامل API ID و API Hash، ورود شماره تلفن، کد تایید و رمز 2FA و قطع اتصال، همه داخل پنل و بدون خروج به صفحه مستقل MadelineProto.
- Studio Settings فقط تنظیمات پردازش ویدیو و Asset ها.
- App Settings شامل هشتگ ها، ربات انتشار با Neili و رمز مدیر.
- رمز API Hash مانند Bot Token رمزگذاری می شود و در HTML مجدد نمایش داده نمی شود.
- تمام Route ها و تنظیمات ذخیره شده قبلی تا حد امکان سازگار مانده اند.

## نصب روی پروژه در حال اجرا

**اول بکاپ بگیر**؛ مخصوصا `.env`, `database/`, `storage/` و اطلاعات اکانت Telegram. سپس Listener و Worker ها را برای اعمال آپدیت متوقف کن.

فایل ZIP آپدیت را داخل مسیر اصلی پروژه Laravel (همان پوشه ای که `artisan` در آن است) Extract کن. ZIP برای همین ریشه ساخته شده است.

```bash
# در مسیر پروژه Laravel
unzip -o /path/to/RealDarkMeme-Laravel-v2.1-Patch.zip
```

اگر `.env` فعلی `APP_LOCALE=fa` دارد آن را به `APP_LOCALE=en` تغییر بده؛ **کل فایل env را جایگزین نکن.** سپس:

```bash
php artisan optimize:clear
php artisan route:list
php artisan test
```

بدون نیاز به `migrate` یا اجرای دوباره installer. اگر از PHP OPcache یا PHP-FPM استفاده می کنی، سرویس مربوط به PHP را Reload کن. بعد Listener و Worker ها را طبق روش قبلی اجرا/Restart کن.

## استفاده

1. از منو **Telegram Account** را باز کن.
2. در قسمت Telegram API credentials، `API ID` و `API Hash` اکانت را از `my.telegram.org` ثبت کن. برای حفظ Hash موجود، فیلد آن را خالی بگذار. این اطلاعات با Bot Token متفاوت هستند.
3. Listener را متوقف نگه دار و شماره تلفن را وارد کن؛ کد Telegram و در صورت نیاز رمز 2FA را در همان صفحه وارد کن.
4. پس از اتصال، Listener را مجدد فعال کن.
5. تنظیمات رندر در **Studio Settings** و هشتگ ها، Bot Token، Channel ID، API URL و تغییر رمز ادمین در **App Settings** هستند.

**نکته:** تغییر API ID/Hash برای یک Session موجود لزوما آن را دوباره احراز هویت نمی کند. برای تعویض اکانت ابتدا Listener را متوقف و از پنل Disconnect کن.

**وضعیت تست:** PHP Syntax و بررسی ساختار فایل ها و Route ها اجرا شده اند. تست کامل Laravel نیازمند `composer install`، اکستنشن SQLite و اجرای `php artisan test` روی سرور است. ورود واقعی تلگرام هم تنها با API ID/Hash و شماره واقعی قابل تست است.
