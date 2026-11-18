<?php

namespace App\Console\Commands;

use App\Support\TelegramCredentials;
use danog\MadelineProto\API;
use Illuminate\Console\Command;
use Throwable;

/** A CLI-only boundary around MadelineProto user authorization. */
class TelegramAuth extends Command
{
    protected $signature = 'rdm:telegram-auth {action : status|phone|code|password|logout}';
    protected $description = 'Internal CLI bridge for manual Telegram authorization';
    private const MARKER = 'RDM_AUTH_RESULT:';

    public function handle(): int
    {
        $action = (string) $this->argument('action');
        $payload = in_array($action, ['status', 'logout'], true)
            ? []
            : json_decode((string) stream_get_contents(STDIN), true);
        if (!is_array($payload)) $payload = [];

        try {
            $result = $this->handleAction($action, $payload);
        } catch (Throwable $e) {
            report($e);
            $result = [
                'ok' => false,
                'state' => 'unknown',
                'message' => $this->safeError($e),
            ];
        }

        // Do not use a Laravel view, redirect or MadelineProto automatic login UI.
        $this->line(self::MARKER.json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE));
        return ($result['ok'] ?? false) ? self::SUCCESS : self::FAILURE;
    }

    private function handleAction(string $action, array $payload): array
    {
        if (!in_array($action, ['status', 'phone', 'code', 'password', 'logout'], true)) {
            return ['ok' => false, 'state' => 'unknown', 'message' => 'Invalid authorization action.'];
        }
        $path = storage_path('app/private/telegram/account.madeline');
        $exists = file_exists($path) || is_dir($path) || file_exists($path.'.lock');
        if ($action === 'status' && !$exists) {
            return ['ok' => true, 'state' => 'not_connected', 'exists' => false, 'me' => null];
        }
        if ($action === 'logout' && !$exists) {
            return ['ok' => false, 'state' => 'not_connected', 'message' => 'No Telegram session exists.'];
        }
        if (!TelegramCredentials::configured()) {
            return ['ok' => $action === 'status', 'state' => 'not_connected', 'exists' => $exists,
                'message' => $exists ? 'Set your Telegram API ID and API hash to inspect the session.' : 'Set Telegram API credentials first.'];
        }
        if (!class_exists(API::class)) {
            return ['ok' => false, 'state' => 'unknown', 'message' => 'MadelineProto is missing. Run composer install.'];
        }

        $api = new API($path, TelegramCredentials::appInfo());
        $current = $api->getAuthorization();

        switch ($action) {
            case 'status':
                return $this->snapshot($api, $exists);

            case 'phone':
                if ($current === API::LOGGED_IN) return $this->snapshot($api, true);
                $phone = $payload['phone'] ?? null;
                if (!is_string($phone) || !preg_match('/^\+[1-9][0-9]{6,14}$/D', $phone)) {
                    return ['ok' => false, 'state' => 'not_connected', 'message' => 'Invalid phone number.'];
                }
                $api->phoneLogin($phone);
                return ['ok' => true, 'state' => 'code', 'message' => 'Telegram login code requested.'];

            case 'code':
                if ($current !== API::WAITING_CODE) {
                    return ['ok' => false, 'state' => $this->state($current), 'message' => 'No login code is pending. Request a new one.'];
                }
                $code = $payload['code'] ?? null;
                if (!is_string($code) || !preg_match('/^[0-9]{3,10}$/D', $code)) {
                    return ['ok' => false, 'state' => 'code', 'message' => 'Invalid verification code.'];
                }
                $result = $api->completePhoneLogin($code);
                $after = $api->getAuthorization();
                if ($after === API::WAITING_PASSWORD || (($result['_'] ?? '') === 'account.password')) {
                    return ['ok' => true, 'state' => 'password', 'message' => 'Enter your Telegram two-step verification password.'];
                }
                if ($after === API::WAITING_SIGNUP || (($result['_'] ?? '') === 'account.needSignup')) {
                    return ['ok' => false, 'state' => 'signup', 'message' => 'Register this account using the official Telegram app first.'];
                }
                if ($after !== API::LOGGED_IN) {
                    return ['ok' => false, 'state' => $this->state($after), 'message' => 'Authorization is not complete.'];
                }
                return $this->snapshot($api, true);

            case 'password':
                if ($current !== API::WAITING_PASSWORD) {
                    return ['ok' => false, 'state' => $this->state($current), 'message' => 'Two-step verification is not pending.'];
                }
                $password = $payload['password'] ?? null;
                if (!is_string($password) || $password === '') {
                    return ['ok' => false, 'state' => 'password', 'message' => 'Enter the Telegram two-step password.'];
                }
                $api->complete2faLogin($password);
                if ($api->getAuthorization() !== API::LOGGED_IN) {
                    return ['ok' => false, 'state' => 'password', 'message' => 'Authorization is not complete.'];
                }
                return $this->snapshot($api, true);

            case 'logout':
                $api->logout();
                return ['ok' => true, 'state' => 'not_connected', 'exists' => false, 'message' => 'Telegram account disconnected.'];
        }
        return ['ok' => false, 'state' => 'unknown', 'message' => 'Unsupported action.'];
    }

    private function snapshot(API $api, bool $exists): array
    {
        $state = $this->state($api->getAuthorization());
        $me = null;
        if ($state === 'connected') {
            $user = $api->getSelf();
            $me = [
                'first_name' => (string) ($user['first_name'] ?? ''),
                'last_name' => (string) ($user['last_name'] ?? ''),
                'username' => (string) ($user['username'] ?? ''),
            ];
        }
        return ['ok' => true, 'state' => $state, 'exists' => $exists, 'me' => $me];
    }

    private function state(mixed $status): string
    {
        return match ($status) {
            API::LOGGED_IN => 'connected',
            API::WAITING_CODE => 'code',
            API::WAITING_PASSWORD => 'password',
            API::WAITING_SIGNUP => 'signup',
            default => 'not_connected',
        };
    }

    private function safeError(Throwable $e): string
    {
        $message = strtoupper($e->getMessage());
        if (str_contains($message, 'PHONE_CODE_INVALID')) return 'The Telegram login code is incorrect.';
        if (str_contains($message, 'PHONE_CODE_EXPIRED')) return 'The Telegram login code has expired. Request a new one.';
        if (str_contains($message, 'PASSWORD_HASH_INVALID')) return 'Incorrect two-step verification password.';
        if (str_contains($message, 'PHONE_NUMBER_INVALID')) return 'Telegram rejected the phone number.';
        if (str_contains($message, 'API_ID_INVALID')) return 'Invalid Telegram API credentials.';
        if (preg_match('/FLOOD_WAIT_([0-9]+)/', $message, $match)) {
            return 'Telegram rate limit: retry after '.$match[1].' seconds.';
        }
        return 'The Telegram operation failed. Check storage/logs/laravel.log for details.';
    }
}
