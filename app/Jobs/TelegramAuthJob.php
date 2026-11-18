<?php

namespace App\Jobs;

use App\Services\TelegramAuthClient;
use App\Services\TelegramAuthState;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/** Passwords and verification codes are encrypted in the queued job payload. */
class TelegramAuthJob implements ShouldQueue, ShouldBeEncrypted
{
    use Queueable;

    public int $tries = 1;
    public int $timeout = 105;

    public function __construct(
        public string $operationId,
        public string $action,
        public array $payload = [],
    ) {
        $this->onConnection('database');
        $this->onQueue('telegram');
    }

    public function handle(TelegramAuthClient $client, TelegramAuthState $state): void
    {
        // Ignore superseded operations. A stale job must never modify an active session.
        if ($state->read()['operation_id'] !== $this->operationId) return;
        $state->update($this->operationId, ['job_state' => 'running', 'message' => 'Communicating with Telegram...']);
        try {
            $result = $client->run($this->action, $this->payload);
            $newState = $result['state'] ?? 'unknown';
            if (!in_array($newState, ['connected', 'not_connected', 'code', 'password', 'signup', 'unknown'], true)) {
                $newState = 'unknown';
            }
            // An invalid code/2FA attempt should not hide the retry form.
            if (!($result['ok'] ?? false) && $newState === 'unknown') {
                $newState = $state->read()['state'];
            }
            $state->update($this->operationId, [
                'state' => $newState,
                'job_state' => ($result['ok'] ?? false) ? 'done' : 'failed',
                'message' => (string) ($result['message'] ?? (($result['ok'] ?? false) ? 'Telegram operation completed.' : 'Telegram operation failed.')),
                'me' => $result['me'] ?? ($newState === 'connected' ? $state->read()['me'] : null),
            ]);
        } catch (Throwable $e) {
            report($e);
            $state->update($this->operationId, [
                'job_state' => 'failed',
                'message' => 'Telegram did not respond in time or the session is locked. Stop the listener, inspect storage/logs/laravel.log, then retry.',
            ]);
        }
    }

    public function failed(?Throwable $e): void
    {
        app(TelegramAuthState::class)->update($this->operationId, [
            'job_state' => 'failed',
            'message' => 'The Telegram worker failed. Check the Laravel log.',
        ]);
    }
}
