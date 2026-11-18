<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * Isolate MadelineProto from PHP-FPM / web requests.
 * The native MadelineProto web login UI must never be able to write to an HTTP response.
 */
class TelegramAuthClient
{
    private const RESULT_PREFIX = 'RDM_AUTH_RESULT:';

    /** @return array{ok:bool,state:string,exists?:bool,me?:array|null,message?:string} */
    public function run(string $action, array $payload = []): array
    {
        if (!in_array($action, ['status', 'phone', 'code', 'password', 'logout'], true)) {
            throw new RuntimeException('Unsupported Telegram authorization action.');
        }

        $php = (string) config('realdarkmeme.php_cli', 'php');
        $process = new Process([$php, base_path('artisan'), 'rdm:telegram-auth', $action], base_path(), [
            'APP_DEBUG' => 'false',
        ]);
        // Credentials and verification codes are sent through stdin, never CLI arguments.
        $process->setInput(json_encode($payload, JSON_THROW_ON_ERROR));
        $process->setTimeout($action === 'status' ? 12 : 90);

        try {
            $process->run();
        } catch (\Throwable $e) {
            Log::warning('Telegram CLI bridge failed to start: '.$e::class);
            throw new RuntimeException('Telegram authentication service is unavailable. Check the configured PHP CLI path and server logs.', 0, $e);
        }

        // MadelineProto may print progress messages; accept only our framed JSON result.
        if (preg_match('/^'.preg_quote(self::RESULT_PREFIX, '/').'([^\r\n]+)$/m', $process->getOutput(), $m) !== 1) {
            Log::warning('Telegram CLI bridge returned no parseable result', ['exit_code' => $process->getExitCode()]);
            throw new RuntimeException('Telegram authentication service returned an invalid response. Check the server logs.');
        }

        $result = json_decode($m[1], true);
        if (!is_array($result) || !isset($result['ok'], $result['state']) || !is_bool($result['ok']) || !is_string($result['state'])) {
            throw new RuntimeException('Telegram authentication service returned an invalid status.');
        }

        return $result;
    }
}
