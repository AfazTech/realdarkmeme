<?php

namespace App\Services;

use App\Jobs\TelegramAuthJob;
use App\Models\Setting;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Fast, database-backed view of Telegram authorization.
 * Reading this state NEVER creates or opens a MadelineProto session.
 */
class TelegramAuthState
{
    private const KEY = 'telegram_auth_state_v23';

    public static function sessionExists(): bool
    {
        $path = storage_path('app/private/telegram/account.madeline');
        return is_file($path) || is_dir($path) || is_file($path.'.lock');
    }

    public function read(): array
    {
        $stored = json_decode((string) Setting::fetch(self::KEY, ''), true);
        if (!is_array($stored)) $stored = [];

        $exists = self::sessionExists();
        $state = $stored['state'] ?? ($exists ? 'unknown' : 'not_connected');
        if (!in_array($state, ['connected', 'not_connected', 'code', 'password', 'signup', 'unknown'], true)) {
            $state = 'unknown';
        }

        $jobState = $stored['job_state'] ?? 'idle';
        $busy = in_array($jobState, ['queued', 'running'], true);
        $timestamp = (string) ($stored['updated_at'] ?? '');
        if ($busy) {
            try { $age = $timestamp !== '' ? abs(now()->diffInSeconds(Carbon::parse($timestamp))) : 9999; }
            catch (\Throwable) { $age = 9999; }
            if ($age > 180) {
                $busy = false;
                $jobState = 'stalled';
            }
        }

        if (!$exists && $state === 'connected') $state = 'not_connected';

        return [
            'state' => $state,
            'exists' => $exists,
            'me' => is_array($stored['me'] ?? null) ? $stored['me'] : null,
            'error' => ($jobState === 'failed' || $jobState === 'stalled')
                ? ($stored['message'] ?? 'Telegram status could not be checked. Please retry.') : null,
            'message' => $stored['message'] ?? null,
            'busy' => $busy,
            'job_state' => $jobState,
            'operation_id' => $stored['operation_id'] ?? '',
            'action' => $stored['action'] ?? null,
        ];
    }

    /** Queue the request in Laravel's database queue, never the web process. */
    public function enqueue(string $action, array $payload = []): void
    {
        if (!in_array($action, ['status', 'phone', 'code', 'password', 'logout'], true)) {
            throw new RuntimeException('Unsupported Telegram operation.');
        }
        $lockFile = storage_path('app/private/telegram/auth-queue.lock');
        if (!is_dir(dirname($lockFile)) && !mkdir(dirname($lockFile), 0700, true) && !is_dir(dirname($lockFile))) {
            throw new RuntimeException('Cannot create Telegram private storage.');
        }
        $handle = fopen($lockFile, 'c');
        if (!$handle) throw new RuntimeException('Cannot lock Telegram authorization state.');
        try {
            if (!flock($handle, LOCK_EX | LOCK_NB)) {
                throw ValidationException::withMessages(['telegram' => 'Another Telegram operation is starting. Try again shortly.']);
            }
            $old = $this->read();
            if ($old['busy']) {
                throw ValidationException::withMessages(['telegram' => 'A Telegram operation is already queued or running.']);
            }
            $id = bin2hex(random_bytes(16));
            $record = [
                'state' => $old['state'], 'me' => $old['me'],
                'job_state' => 'queued', 'action' => $action,
                'operation_id' => $id, 'updated_at' => now()->toIso8601String(),
                'message' => 'Waiting for the Telegram worker...',
            ];
            $this->save($record);
            try {
                // An explicit database connection also prevents an accidental QUEUE_CONNECTION=sync.
                TelegramAuthJob::dispatch($id, $action, $payload)->onConnection('database')->onQueue('telegram');
            } catch (\Throwable $e) {
                $record['job_state'] = 'failed';
                $record['updated_at'] = now()->toIso8601String();
                $record['message'] = 'The Telegram queue is unavailable. Verify the jobs table and database connection.';
                $this->save($record);
                throw $e;
            }
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    public function update(string $id, array $changes): void
    {
        $stored = json_decode((string) Setting::fetch(self::KEY, ''), true);
        if (!is_array($stored) || ($stored['operation_id'] ?? '') !== $id) return;
        $this->save(array_merge($stored, $changes, ['updated_at' => now()->toIso8601String()]));
    }

    private function save(array $record): void
    {
        Setting::store(self::KEY, json_encode($record, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }
}
