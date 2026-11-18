<?php
namespace Tests\Feature;

use App\Jobs\{ProcessVideo,PublishVideo};
use App\Models\{User,Channel,Video,Render,Publication,Setting};
use App\Support\{BotSettings,RenderSettings};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Queue,Storage};
use Tests\TestCase;

class StudioTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_requires_valid_credentials(): void
    {
        User::create(['username'=>'admin','password'=>'secret']);
        $this->post('/login',['username'=>'admin','password'=>'wrong'])->assertSessionHasErrors('username');
        $this->post('/login',['username'=>'admin','password'=>'secret'])->assertRedirect('/');
    }
    public function test_channel_add_uses_real_peer_id(): void
    {
        $admin=User::create(['username'=>'admin','password'=>'x']);
        $this->actingAs($admin)->post('/channels',['peer_id'=>'@username','label'=>'Test'])->assertSessionHasErrors('peer_id');
        $this->actingAs($admin)->post('/channels',['peer_id'=>'-1001234567890','label'=>'Test'])->assertSessionHas('ok');
        $this->assertDatabaseHas('channels',['peer_id'=>'-1001234567890','identifier'=>'-1001234567890']);
        $this->actingAs($admin)->get(route('channels.index'))->assertOk()->assertDontSee('Username / identifier');
    }
    public function test_assets_show_previews_in_settings_and_are_auth_protected(): void
    {
        Storage::fake('private');
        $admin=User::create(['username'=>'admin','password'=>'secret']);
        $this->actingAs($admin)->get(route('settings.index'))
            ->assertOk()->assertSee('No image configured yet');
        // A tiny PNG fixture; previews never expose a public storage path.
        $png=base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQIHWP4z8DwHwAFgAI/ScL/nwAAAABJRU5ErkJggg==');
        Storage::disk('private')->put('assets/thumbnail.png',$png);
        $this->actingAs($admin)->get(route('settings.index'))
            ->assertOk()->assertSee('Configured')->assertSee(route('settings.assets.preview','thumbnail').'?v=',false);
        $this->get(route('settings.assets.preview','thumbnail'))
            ->assertOk()->assertHeader('Content-Type','image/png');
        $this->get(route('settings.assets.preview','outro'))->assertNotFound();
        auth()->logout();
        $this->get(route('settings.assets.preview','thumbnail'))->assertRedirect(route('login'));
    }

    public function test_remake_queues_new_render_from_original(): void
    {
        Queue::fake();Storage::fake('private');
        $admin=User::create(['username'=>'admin','password'=>'x']);
        $channel=Channel::create(['peer_id'=>'-1001234567890','identifier'=>'@source','label'=>'Source','enabled'=>true]);
        $v=Video::create(['channel_id'=>$channel->id,'telegram_message_id'=>100,'source_path'=>'inputs/1.mp4','status'=>'ready']);
        Storage::disk('private')->put('inputs/1.mp4',str_repeat('x',3000));
        $settings=RenderSettings::current();
        $this->actingAs($admin)->post(route('videos.remake',$v),['title'=>'کاور جدید','caption_body'=>'کپشن جدید','settings'=>$settings])->assertSessionHas('ok');
        $this->assertDatabaseHas('videos',['id'=>$v->id,'status'=>'queued','title'=>'کاور جدید']);
        Queue::assertPushed(ProcessVideo::class);
    }
    public function test_approve_is_version_scoped_and_unique(): void
    {
        Queue::fake();Storage::fake('private');
        $admin=User::create(['username'=>'admin','password'=>'x']);
        $channel=Channel::create(['peer_id'=>'-1001234567890','identifier'=>'@source','label'=>'Source','enabled'=>true]);
        $v=Video::create(['channel_id'=>$channel->id,'telegram_message_id'=>100,'status'=>'ready']);
        $render=Render::create(['video_id'=>$v->id,'version'=>1,'status'=>'ready','output_path'=>'outputs/1/v1.mp4','title'=>'title','caption'=>'sample','settings_snapshot'=>[]]);
        Storage::disk('private')->put('outputs/1/v1.mp4',str_repeat('x',3000));
        $v->update(['current_render_id'=>$render->id]);
        BotSettings::save(['bot_token'=>'123456789:ABCDEFGHIJKLMNOPQRSTUVWXYZ123456','bot_chat'=>'@destination','bot_api_url'=>'https://api.telegram.org/bot']);
        $this->actingAs($admin)->post(route('videos.approve',$v))->assertSessionHas('ok');
        $this->actingAs($admin)->post(route('videos.approve',$v))->assertSessionHasErrors('publish');
        $this->assertSame(1,Publication::count());
        Queue::assertPushed(PublishVideo::class,1);
    }
    public function test_settings_are_split_and_all_new_screens_use_english(): void
    {
        $admin=User::create(['username'=>'admin','password'=>'secret']);
        $this->actingAs($admin)->get(route('settings.index'))
            ->assertOk()->assertSee('Studio Settings')->assertDontSee('Bot token');
        $this->actingAs($admin)->get(route('settings.general'))
            ->assertOk()->assertSee('App Settings')->assertSee('Bot token')->assertSee('Default hashtags');
        $this->actingAs($admin)->get(route('telegram.index'))
            ->assertOk()->assertSee('Phone number')->assertSee('Telegram API credentials');
    }

    public function test_hashtags_are_saved_separately_from_render_options(): void
    {
        $admin=User::create(['username'=>'admin','password'=>'secret']);
        $this->actingAs($admin)->post(route('settings.hashtags'),['hashtags'=>'#one #two'])
            ->assertSessionHas('ok');
        $this->assertSame('#one #two', Setting::fetch('hashtags'));
        $this->actingAs($admin)->post(route('settings.render'),['settings'=>RenderSettings::current()])
            ->assertSessionHas('ok');
        $this->assertSame('#one #two', Setting::fetch('hashtags'));
    }

    public function test_telegram_api_credentials_are_not_rendered_in_html(): void
    {
        $admin=User::create(['username'=>'admin','password'=>'secret']);
        $hash='1234567890abcdef1234567890abcdef';
        $this->actingAs($admin)->post(route('telegram.credentials'),['api_id'=>12345,'api_hash'=>$hash])
            ->assertSessionHas('ok');
        $this->assertSame('12345',Setting::fetch('telegram_api_id'));
        $this->assertNotSame($hash,Setting::fetch('telegram_api_hash'));
        $this->actingAs($admin)->get(route('telegram.index'))->assertOk()->assertDontSee($hash);
    }

    public function test_phone_login_requires_credentials(): void
    {
        $admin=User::create(['username'=>'admin','password'=>'secret']);
        $this->actingAs($admin)->post(route('telegram.login.phone'),['phone'=>'+491234567890'])
            ->assertSessionHasErrors('phone');
    }
}
