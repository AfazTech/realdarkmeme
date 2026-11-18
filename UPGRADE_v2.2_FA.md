# RealDarkMeme Studio — v2.2 Telegram panel fix

This patch fixes the Telegram Account screen opening MadelineProto's own login UI instead of staying in the Laravel studio.

## What changed

- The `/telegram` web page no longer instantiates `danog\MadelineProto\API` inside PHP-FPM.
- Phone / verification code / 2FA / logout operations now run through a dedicated `rdm:telegram-auth` CLI command, invoked by a Symfony Process bridge. Secrets go over **stdin**, never CLI arguments.
- Status is read from the CLI process and rendered in the same Laravel screen.
- Studio's other features, database, existing MadelineProto sessions, bot credentials and stored videos remain untouched.
- Added route integration tests using a mock Telegram CLI bridge; actual live Telegram login still requires production testing.

## Install (existing project located in `realdarkmeme/`)

Stop your listener before applying the patch and take a backup of project code and `storage/`.

```bash
cd ~/Desktop
unzip -o ~/Downloads/realdarkmeme-patch-v2.2.zip
cd realdarkmeme
php artisan optimize:clear
php artisan route:clear
```

If you use PHP-FPM or OPcache on a server, reload PHP-FPM after the file overwrite. Restart your listener after completing the login inside the admin panel. You do **not** need to run `migrate`, `composer update`, or reset any session files.

If the web server cannot find PHP CLI on PATH, add `RDM_PHP_CLI=/usr/bin/php` (or your PHP CLI path) to `.env`, then run `php artisan optimize:clear`.

Log into the panel and open **Telegram Account**. You should see the API ID/hash and phone fields inside the studio. Submit the phone, then the code and 2FA password on that same page.

If errors occur, check `storage/logs/laravel.log` and verify the web process user can run `php artisan rdm:telegram-auth status` from the project folder and write to `storage/app/private/telegram`.

## Important

- Use the same OS user/group permissions for the web process and the listener to share session files safely.
- Only use HTTPS for the admin interface in production.
- Do not start `rdm:listen` while signing in or disconnecting.
- No session files are overwritten by this patch.
