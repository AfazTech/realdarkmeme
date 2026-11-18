# RealDarkMeme - Separate Settings & IP Security

Changes:
- Senders has its own sidebar menu and /senders page for multiple Telegram destinations.
- Studio Settings is an overview; each rendering group (Cover, Watermark, Video, Outro, Output) has a dedicated page and saves independently.
- Media Assets has a separate page showing existing cover/outro previews.
- App Settings is an overview; Caption Defaults and Admin Security have separate pages.
- Admin Security has optional allowlisted IPv4/IPv6 addresses and CIDR ranges (up to 50 entries); login and all admin routes are blocked if the current trusted client IP is not permitted.

## Deployment
Back up your database and .env, then:
```bash
cd ~/Desktop/realdarkmeme
git pull --ff-only origin main
php artisan optimize:clear
php artisan queue:restart
```

Restart your Laravel web process if needed. No new Composer packages or database migrations are required.

## Safe IP setup
1. Visit App Settings > Admin Security. The current detected IP is shown.
2. Enter IP addresses or CIDR ranges, one per line. IPv4 and IPv6 are supported. Enter your current IP as well.
3. Enable IP restriction and enter your existing administrator password to save.
4. Confirm that you can still log in from the permitted network.

Important: the detected IP is Laravel's request IP. With a reverse proxy, configure trusted proxies securely first; otherwise it may be the reverse proxy address. Do not trust unverified X-Forwarded-For headers. The site does not automatically enable the allowlist when upgrading.

If your IP changes and you lose access, log in to the server locally and run:
```bash
cd ~/Desktop/realdarkmeme
php artisan rdm:ip-unlock
```
Confirm when prompted. This disables the allowlist from the server console.

## Verification
```bash
php artisan test --filter=StudioTest
php artisan test --filter=SendersTest
```

The former routes /settings and /app-settings remain as navigation pages. Existing saved render settings, Telegram senders, assets and publication history are preserved.
