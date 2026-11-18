# RealDarkMeme Studio 2.3 — Fast Telegram Account page

## Root cause

The previous Telegram page synchronously called `TelegramAuthClient::run('status')` on every GET. The call starts an Artisan subprocess and opens MadelineProto's session, potentially blocking behind MTProto/session locks and external I/O. On the PHP development server, blocked requests also delay unrelated pages.

## Changes

- GET `/telegram` and GET `/telegram/status` read *only local* state; neither opens a MadelineProto session.
- The Telegram login / status-check / logout operations are encrypted queued jobs processed by a dedicated `telegram` queue worker.
- The Telegram page polls the local job state and refreshes when the job completes.
- A timeout/failure now appears inside the Telegram page rather than blocking every page request.
- No Telegram session, API token, `.env`, previous video or production database is packaged or deleted.
- Verification codes and 2FA passwords are encrypted in the database queue payload through Laravel's `ShouldBeEncrypted` contract. The final state stores **no passwords or verification codes**.

## Upgrade existing project

Stop the Telegram listener during authentication if it is running. Back up your existing project first.

From the parent of the `realdarkmeme` directory, extract `realdarkmeme-patch-v2.3.zip`, overwriting files. For a local setup:

```bash
cd ~/Desktop
unzip -o ~/Downloads/realdarkmeme-patch-v2.3.zip
cd realdarkmeme
php artisan optimize:clear
```

Start a **separate terminal** for Telegram authorization jobs:

```bash
cd ~/Desktop/realdarkmeme
php artisan queue:work database --queue=telegram --sleep=1 --tries=1 --timeout=110
```

Keep this worker running while signing into Telegram. Existing render, publish and listener workers are unchanged.

For systemd installations, `deploy/rdm-telegram.service` is a sample. Adjust the service user, PHP binary and WorkingDirectory to your own server before enabling it. Do not run the sample unchanged if those paths differ.

No Composer update or schema migration is required. Restart long-running PHP workers if you changed code.

## Troubleshooting

- If a Telegram operation remains queued, ensure the **telegram** queue worker is running. The UI will report a stalled operation after 3 minutes.
- If the operation fails, stop the listener and inspect `storage/logs/laravel.log`. Do not delete the Telegram session to address a timeout.
- An existing legacy session is initially displayed as **not verified**; use **Check session status** from inside the panel while the listener is stopped.
- For low-level debugging only, with the listener stopped: `timeout 20s php artisan rdm:telegram-auth status`. This can help distinguish a corrupted session, a session lock, and Telegram connectivity.
- Laravel's built-in development server handles requests sequentially. The 2.3 GET page no longer makes blocking Telegram calls.

## Verification

- GET `/telegram` returns without starting MadelineProto or invoking CLI.
- Save API credentials, request login code, enter code and optional 2FA from the studio.
- The page shows `queued` and `running` without blocking. On completion, it automatically reloads the current step.
- `php artisan test` runs feature tests if Composer dependencies are installed.

