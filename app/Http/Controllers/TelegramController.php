<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Services\TelegramAuthState;
use App\Support\TelegramCredentials;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;
use Throwable;

class TelegramController extends Controller
{
    private function assertListenerStopped(): void
    {
        $path = storage_path('app/private/telegram/listener.lock');
        if (!file_exists($path)) return;
        $handle = @fopen($path, 'r');
        if (!$handle || !flock($handle, LOCK_EX | LOCK_NB)) {
            if ($handle) fclose($handle);
            throw ValidationException::withMessages(['telegram' => 'Stop the Telegram listener before changing or checking the account.']);
        }
        flock($handle, LOCK_UN);
        fclose($handle);
    }

    /** GET is always fast: it only reads local SQLite and filesystem metadata. */
    public function index(TelegramAuthState $auth)
    {
        $snapshot = $auth->read();
        return view('telegram.index', [
            'exists' => $snapshot['exists'],
            'state' => $snapshot['state'],
            'me' => $snapshot['me'],
            'error' => $snapshot['error'],
            'busy' => $snapshot['busy'],
            'job_state' => $snapshot['job_state'],
            'operation_message' => $snapshot['message'],
            'api_id' => TelegramCredentials::apiId(),
            'credentials_set' => TelegramCredentials::configured(),
        ]);
    }

    /** Polled by the page; never calls MadelineProto. */
    public function status(TelegramAuthState $auth)
    {
        $snapshot = $auth->read();
        return response()->json([
            'state' => $snapshot['state'],
            'busy' => $snapshot['busy'],
            'job_state' => $snapshot['job_state'],
            'operation_id' => $snapshot['operation_id'],
        ])->header('Cache-Control', 'no-store');
    }

    /** Explicit session check, queued to a separate worker. */
    public function refresh(TelegramAuthState $auth)
    {
        $this->assertListenerStopped();
        if (!TelegramCredentials::configured()) {
            throw ValidationException::withMessages(['telegram' => 'Save Telegram API credentials first.']);
        }
        $this->enqueue($auth, 'status');
        return redirect()->route('telegram.index')->with('ok', 'Telegram status check queued.');
    }

    public function credentials(Request $request)
    {
        $v = $request->validate([
            'api_id' => ['required', 'integer', 'between:1000,999999999999'],
            'api_hash' => ['nullable', 'string', 'regex:/^[0-9a-fA-F]{32}$/D'],
        ]);
        $this->assertListenerStopped();
        TelegramCredentials::save((int) $v['api_id'], $v['api_hash'] ?? null);
        ActivityLog::record('telegram.credentials.updated', 'Telegram API credentials updated (hash hidden)', auth()->id());
        return redirect()->route('telegram.index')->with('ok', 'Telegram API credentials saved.');
    }

    public function phone(Request $request, TelegramAuthState $auth)
    {
        $v = $request->validate(['phone' => ['required', 'string', 'regex:/^\+[1-9][0-9]{6,14}$/D']]);
        $this->assertListenerStopped();
        if (!TelegramCredentials::configured()) {
            throw ValidationException::withMessages(['phone' => 'Configure a Telegram API ID and hash before signing in.']);
        }
        $this->enqueue($auth, 'phone', ['phone' => $v['phone']]);
        ActivityLog::record('telegram.login.code_queued', 'Telegram login code request queued', auth()->id());
        return redirect()->route('telegram.index')->with('ok', 'Login code request queued.');
    }

    public function code(Request $request, TelegramAuthState $auth)
    {
        $v = $request->validate(['code' => ['required', 'string', 'regex:/^[0-9]{3,10}$/D']]);
        $this->assertListenerStopped();
        $this->enqueue($auth, 'code', ['code' => $v['code']]);
        return redirect()->route('telegram.index')->with('ok', 'Verification queued.');
    }

    public function password(Request $request, TelegramAuthState $auth)
    {
        $v = $request->validate(['telegram_password' => ['required', 'string']]);
        $this->assertListenerStopped();
        $this->enqueue($auth, 'password', ['password' => $v['telegram_password']]);
        return redirect()->route('telegram.index')->with('ok', 'Two-step verification queued.');
    }

    public function disconnect(Request $request, TelegramAuthState $auth)
    {
        $request->validate(['confirm' => ['required', 'accepted']]);
        $this->assertListenerStopped();
        $this->enqueue($auth, 'logout');
        ActivityLog::record('telegram.disconnect_queued', 'Telegram logout queued', auth()->id());
        return redirect()->route('telegram.index')->with('ok', 'Telegram logout queued.');
    }

    private function enqueue(TelegramAuthState $auth, string $action, array $payload = []): void
    {
        try {
            $auth->enqueue($action, $payload);
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            report($e);
            throw ValidationException::withMessages(['telegram' => 'Could not queue Telegram operation. Check database and Laravel logs.']);
        }
    }
}
