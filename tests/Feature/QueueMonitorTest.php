<?php
declare(strict_types=1);

namespace Tests\Feature;

use App\Models\{Channel,Publication,Render,Sender,User,Video};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class QueueMonitorTest extends TestCase
{
    use RefreshDatabase;

    private function login(): User
    {
        $user=User::create(['username'=>'queue-admin','password'=>'secret']);
        $this->actingAs($user);
        return $user;
    }

    public function test_monitor_requires_authentication(): void
    {
        $this->get(route('queues.index'))->assertRedirect(route('login'));
    }

    public function test_uncertain_publications_show_details_and_duplicate_warning(): void
    {
        $admin=$this->login();
        $channel=Channel::create(['peer_id'=>'-1001234567890','label'=>'Source','enabled'=>true]);
        $video=Video::create(['channel_id'=>$channel->id,'telegram_message_id'=>1,'status'=>'ready']);
        $render=Render::create(['video_id'=>$video->id,'version'=>1,'status'=>'ready',
            'caption'=>'Test caption','settings_snapshot'=>[]]);
        $sender=Sender::create(['name'=>'Main','type'=>'telegram','enabled'=>true,
            'token'=>'123456789:ABCDEFGHIJKLMNOPQRSTUVWXYZ123456','destination'=>'@destination',
            'api_url'=>'https://api.telegram.org/bot']);
        $publication=Publication::create([
            'video_id'=>$video->id,'render_id'=>$render->id,'sender_id'=>$sender->id,
            'destination'=>'@destination','status'=>'uncertain','attempts'=>1,
            'approved_by'=>$admin->id,'started_at'=>now()->subMinutes(5),
            'error'=>'Telegram response timeout',
        ]);
        $this->get(route('queues.index'))
            ->assertOk()->assertSee('publish.uncertain')
            ->assertSee('Telegram response timeout')
            ->assertSee('@destination')
            ->assertSee('duplicate-post risk')
            ->assertSee(route('publications.retry',$publication),false);

        $this->post(route('publications.retry',$publication))->assertSessionHasErrors('publish');
        $this->assertSame('uncertain',$publication->fresh()->status);
    }

    public function test_queued_job_payload_is_never_exposed(): void
    {
        $this->login();
        $secret='PRIVATE-TELEGRAM-LOGIN-CODE';
        DB::table('jobs')->insert([
            'queue'=>'telegram',
            'payload'=>json_encode([
                'displayName'=>'App\\Jobs\\TelegramAuthJob',
                'data'=>['password'=>$secret],
            ],JSON_THROW_ON_ERROR),
            'attempts'=>0,'reserved_at'=>null,'available_at'=>time(),'created_at'=>time(),
        ]);
        $this->get(route('queues.index',['tab'=>'jobs']))
            ->assertOk()->assertSee('TelegramAuthJob')
            ->assertDontSee($secret)->assertSee('Waiting');
    }

    public function test_failed_job_reports_redacted_error_without_payload(): void
    {
        $this->login();
        $secret='123456789:ABCDEFGHIJKLMNOPQRSTUVWXYZ123456';
        DB::table('failed_jobs')->insert([
            'uuid'=>(string)\Illuminate\Support\Str::uuid(),
            'connection'=>'database','queue'=>'publish',
            'payload'=>'{"password":"HIDDEN-QUEUE-PAYLOAD"}',
            'exception'=>"RuntimeException: token: ".$secret."\nStack trace: /private/path",
            'failed_at'=>now(),
        ]);
        $this->get(route('queues.index',['tab'=>'failed']))
            ->assertOk()->assertSee('REDACTED')
            ->assertDontSee($secret)
            ->assertDontSee('HIDDEN-QUEUE-PAYLOAD')
            ->assertDontSee('Stack trace:');
    }

    public function test_render_history_shows_result_and_issue(): void
    {
        $this->login();
        $channel=Channel::create(['peer_id'=>'-1001234567890','label'=>'Source','enabled'=>true]);
        $video=Video::create(['channel_id'=>$channel->id,'telegram_message_id'=>3,'status'=>'failed']);
        Render::create(['video_id'=>$video->id,'version'=>1,'status'=>'failed',
            'caption'=>'','error'=>'FFmpeg exited with code 1','settings_snapshot'=>[]]);
        $this->get(route('queues.index',['tab'=>'renders']))
            ->assertOk()->assertSee('Render history')->assertSee('FFmpeg exited with code 1');
    }

    public function test_theme_selector_and_stylesheet_are_available(): void
    {
        $this->login();
        $this->get(route('queues.index'))
            ->assertOk()->assertSee('themePreference')->assertSee('System')
            ->assertSee('Light')->assertSee('Dark')
            ->assertSee('theme.css')->assertSee('queue-monitor.css');
        auth()->logout();
        $this->get(route('login'))->assertOk()->assertSee('theme.css');
    }
}
