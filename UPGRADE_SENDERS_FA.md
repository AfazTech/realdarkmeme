# RealDarkMeme - Multi-Sender Upgrade

Stop the Telegram listener and publish/render queue workers. Back up the SQLite database and .env.

Update:

    cd ~/Desktop/realdarkmeme
    git pull --ff-only origin main
    php artisan optimize:clear
    php artisan migrate --force
    php artisan queue:restart

Restart the existing workers and Telegram listener after migration. No Composer update is required.

- Source Channels now require only a name and numeric peer ID; the legacy identifier column is dropped.
- Existing publishing bot settings and publication history are migrated to a Telegram Sender.
- Configure multiple independent Telegram bots in App Settings > Senders.
- After approval, one publication is queued for each enabled Sender, each with separate status and message ID.
- Disabled senders do not accept new deliveries. Queued jobs pause until re-enabled.
- MadelineProto event handlers now use strict_types as required.

Important: The migration is one-way to prevent data loss from multiple publications per render.
If Telegram authentication still fails, stop the listener and inspect storage/logs/laravel.log and MadelineProto.log. Preserve session files and keep credentials private.
