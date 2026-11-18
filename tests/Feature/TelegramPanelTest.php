<?php

namespace Tests\Feature;

use App\Jobs\TelegramAuthJob;
use App\Models\Setting;
use App\Models\User;
use App\Services\TelegramAuthClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class TelegramPanelTest extends TestCase
{
    use RefreshDatabase;

    public function test_page_and_settings_do_not_start_cli_on_get(): void
    {
        $auth = \Mockery::mock(TelegramAuthClient::class);
        $auth->shouldNotReceive('run');
        $this->app->instance(TelegramAuthClient::class, $auth);
        $admin = User::create(['username' => 'admin', 'password' => 'test']);

        $this->actingAs($admin)->get('/telegram')
            ->assertOk()->assertSee('Sign in from the studio')
            ->assertDontSee('Log in to MadelineProto');
        $this->actingAs($admin)->get('/settings')->assertOk();
        $this->actingAs($admin)->get('/app-settings')->assertOk();
    }

    public function test_stored_code_step_displays_without_contacting_madeline(): void
    {
        Setting::store('telegram_auth_state_v23', json_encode([
            'state' => 'code', 'job_state' => 'done', 'operation_id' => 'test', 'updated_at' => now()->toIso8601String(),
        ]));
        $admin = User::create(['username' => 'admin', 'password' => 'test']);
        $this->actingAs($admin)->get('/telegram')->assertOk()->assertSee('Enter verification code');
    }

    public function test_phone_login_is_enqueued_not_executed_inside_web_request(): void
    {
        Queue::fake();
        $admin = User::create(['username' => 'admin', 'password' => 'test']);
        $this->actingAs($admin)->post('/telegram/credentials', [
            'api_id' => 12345, 'api_hash' => '0123456789abcdef0123456789abcdef',
        ])->assertSessionHas('ok');

        $this->actingAs($admin)->post('/telegram/login/phone', [
            'phone' => '+491234567890',
        ])->assertRedirect('/telegram')->assertSessionHas('ok');
        Queue::assertPushed(TelegramAuthJob::class, function (TelegramAuthJob $job) {
            return $job->action === 'phone' && ($job->payload['phone'] ?? '') === '+491234567890';
        });
        $this->actingAs($admin)->get('/telegram')
            ->assertOk()->assertSee('Telegram operation queued');
    }

    public function test_duplicate_telegram_operations_are_rejected(): void
    {
        Queue::fake();
        $admin = User::create(['username' => 'admin', 'password' => 'test']);
        $this->actingAs($admin)->post('/telegram/credentials', [
            'api_id' => 12345, 'api_hash' => '0123456789abcdef0123456789abcdef',
        ]);
        $this->actingAs($admin)->post('/telegram/login/phone', ['phone' => '+491234567890']);
        $this->actingAs($admin)->post('/telegram/login/phone', ['phone' => '+491234567890'])
            ->assertSessionHasErrors('telegram');
        Queue::assertPushed(TelegramAuthJob::class, 1);
    }

    public function test_status_endpoint_uses_cached_state_and_requires_auth(): void
    {
        $this->get('/telegram/status')->assertRedirect('/login');
        $admin = User::create(['username' => 'admin', 'password' => 'test']);
        $this->actingAs($admin)->get('/telegram/status')->assertOk()
            ->assertJsonStructure(['state', 'busy', 'job_state', 'operation_id']);
    }
}
