# معماری RealDarkMeme Laravel

## مسیر داده

1. `rdm:listen` کانال های فعال را از دیتابیس می خواند. `VideoEventHandler` فقط `Incoming&ChannelMessage&HasMedia` و انواع Video/Document با MIME ویدیو را می پذیرد.
2. `channel_id + telegram_message_id` در دیتابیس Unique است؛ هر ویدیو یک بار دریافت می شود.
3. فایل اصلی در `storage/app/private/inputs` ذخیره می شود و Job `ProcessVideo` در صف `render` قرار می گیرد.
4. Worker با FFmpeg فریم اول را استخراج می کند؛ Tesseract فقط `fas` را OCR می کند؛ نویسه های غیر فارسی حذف می شوند.
5. تنظیمات جهانی + Override های ویدیو ادغام می شوند؛ اسکریپت `engine/render_job.py` همان موتور اصلی `make.py` را فراخوانی می کند.
6. خروجی و پوستر در پوشه خصوصی نسخه بندی شده قرار می گیرد. وضعیت نهایی `ready/failed` است. تنظیمات هر نسخه Snapshot می شوند.
7. صفحه جزئیات اجازه Preview، Copy Caption، Download و Remake مستقل از فایل اصلی را می دهد.
8. با Approve یک رکورد Unique روی `render_id` در `publications` ساخته می شود. Job `PublishVideo` از طریق Neili و Bot API ارسال می کند.
9. `sent` همراه `message_id` ثبت می شود؛ اختلال پس از شروع ارتباط به `uncertain` تبدیل شده و نیازمند بررسی دستی است.

## نکات طراحی

- سرور وب تنها مسئول فرم ها و نمایش است؛ پردازش ها داخل HTTP اجرا نمی شوند.
- در مدل `Video` نسخه جاری با `current_render_id` نگه داشته می شود و همه نسخه ها در `renders` باقی می مانند.
- برای هر ویدیو یک file-lock وجود دارد تا Worker های همزمان یک فایل را رندر نکنند.
- `BotSettings` توکن را با `Crypt` نگه می دارد و آن را به HTML باز نمی گرداند.
- محدودیت کپشن Telegram قبل از تایید بررسی می شود.
- مسیر فایل ویدیو از دیتابیس و Disk خصوصی می آید، نه از مقدار دلخواه درخواست.
- UI با Blade و فایل CSS/JS اختصاصی بدون Node build ارائه شده است.

## ترتیب استقرار

Development -> Composer + migrations -> Preview در staging -> Healthcheck -> Login Telegram -> Channel selection -> queue workers -> Preview/Remake -> Bot test -> Approve test -> cutover.

از انتقال مستقیم روی نسخه قدیمی یا اشتراک دیتابیس SQLite قبلی با Laravel خودداری کن.
