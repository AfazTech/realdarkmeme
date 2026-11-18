# RealDarkMeme — Theme and Queue Monitor

## Features

- Light / Dark / System theme dropdown in the top bar. System follows OS theme changes.
- Preference stored in browser localStorage, not the database. The sign-in screen respects the saved preference.
- Queue Monitor in the sidebar at /queues (requires admin authentication and respects IP restrictions).
- Publication records show status, sender, destination, attempts, timestamps, Telegram message IDs, and error summary.
- publish.uncertain is highlighted and never automatically retried. Check destination and confirm duplicate risk before retry.
- Pending Laravel database jobs show queue, type, attempt count, claimed/waiting state. Payloads are hidden.
- Failed Laravel jobs show sanitized first-line errors; raw payloads and stack traces are never displayed.
- Render history and most recent Telegram authentication state are available in separate tabs.
- Completed Laravel jobs are removed from jobs. Results come from persistent application records.

## Update

    cd ~/Desktop/realdarkmeme
    git pull --ff-only origin main
    php artisan optimize:clear
    php artisan queue:restart

Restart web/queue processes if not supervised. No migrations and no Composer changes are needed.

## Queues to keep running

    php artisan queue:work database --queue=telegram --sleep=1 --tries=1 --timeout=110
    php artisan queue:work database --queue=render --sleep=3 --tries=1 --timeout=3600
    php artisan queue:work database --queue=publish --sleep=3 --tries=1 --timeout=1800

Keep the Telegram listener (rdm:listen) separate. Stop it when changing Telegram authorization.

Queue Monitor is an inspection tool. It cannot guarantee a delivery outcome after a Telegram network timeout.
Always review the destination before retrying any uncertain delivery.

## Tests

    php artisan test --filter=QueueMonitorTest
