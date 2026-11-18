# RealDarkMeme Studio v2.4 — آپدیت طراحی و کانال ها

- در Studio Settings بخش Cover and outro images اکنون تصاویر فعلی از دیسک private را به شکل پیش نمایش نشان می دهد.
- برای دریافت پیش نمایش از Route احراز هویت شده استفاده می شود؛ لینک مستقیم به storage عمومی اضافه نشده است.
- اندازه و آخرین تاریخ تغییر هر تصویر نمایش داده می شود. بعد از جایگزینی فایل، پیش نمایش جدید دیده می شود.
- در Source Channels ورودی Username حذف شده است. فقط Display name و Numeric peer ID الزامی هستند.
- ستون identifier در دیتابیس قدیمی حفظ می شود و در ایجاد کانال جدید به صورت خودکار با peer_id پر می شود؛ بنابراین Migration لازم نیست.

## اعمال Patch

از خارج پوشه پروژه:

```bash
cd ~/Desktop
unzip -o ~/Downloads/realdarkmeme-patch-v2.4.zip
cd realdarkmeme
php artisan optimize:clear
```

فایل های موجود در storage/app/private و .env داخل ZIP نیستند و تغییر نمی کنند.
برای بازگشت تصاویر پیش فرض در نصب جدید، در صورت نبود فایل ها از `php artisan rdm:prepare` استفاده کن؛ این فرمان فایل های موجود را overwrite نمی کند.
