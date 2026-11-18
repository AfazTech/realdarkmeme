<?php
namespace Tests\Feature;

use App\Jobs\{ProcessVideo,PublishVideo};
use App\Models\{User,Channel,Video,Render,Publication,Setting,Sender};
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
        $this->assertDatabaseHas('channels',['peer_id'=>'-1001234567890']);
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
        $channel=Channel::create(['peer_id'=>'-1001234567890','label'=>'Source','enabled'=>true]);
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
        $channel=Channel::create(['peer_id'=>'-1001234567890','label'=>'Source','enabled'=>true]);
        $v=Video::create(['channel_id'=>$channel->id,'telegram_message_id'=>100,'status'=>'ready']);
        $render=Render::create(['video_id'=>$v->id,'version'=>1,'status'=>'ready','output_path'=>'outputs/1/v1.mp4','title'=>'title','caption'=>'sample','settings_snapshot'=>[]]);
        Storage::disk('private')->put('outputs/1/v1.mp4',str_repeat('x',3000));
        $v->update(['current_render_id'=>$render->id]);
        Sender::create(['name'=>'Main','type'=>'telegram','enabled'=>true,'token'=>'123456789:ABCDEFGHIJKLMNOPQRSTUVWXYZ123456','destination'=>'@destination','api_url'=>'https://api.telegram.org/bot']);
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
            ->assertOk()->assertSee('App Settings')->assertSee('Senders')->assertSee('Default hashtags');
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

    public function test_caption_can_be_saved_without_changing_the_overlay_or_queueing_render(): void
    {
        Queue::fake(); Storage::fake('private');
        $admin=User::create(['username'=>'admin','password'=>'secret']);
        Setting::store('hashtags','#reels #meme');
        $channel=Channel::create(['peer_id'=>'-1001234567890','label'=>'Source','enabled'=>true]);
        $video=Video::create([
            'channel_id'=>$channel->id,'telegram_message_id'=>203,'title'=>'Overlay title',
            'caption_body'=>'Old caption','caption'=>'Old caption','status'=>'ready'
        ]);
        $render=Render::create([
            'video_id'=>$video->id,'version'=>1,'status'=>'ready','title'=>'Overlay title',
            'caption'=>'Old caption','output_path'=>'outputs/203-v1.mp4','settings_snapshot'=>[]
        ]);
        $video->update(['current_render_id'=>$render->id]);
        $this->actingAs($admin)->get(route('videos.show',$video))
            ->assertOk()->assertSee('Save caption')->assertSee('Approve &amp; Publish',false);
        $this->actingAs($admin)->post(route('videos.caption',$video),['caption_body'=>'New caption'])
            ->assertSessionHas('ok');
        $this->assertSame("New caption\n\n#reels #meme",$video->fresh()->caption);
        $this->assertSame("New caption\n\n#reels #meme",$render->fresh()->caption);
        $this->assertSame('Overlay title',$video->fresh()->title);
        $this->assertSame('Overlay title',$render->fresh()->title);
        $this->assertSame($render->id,$video->fresh()->current_render_id);
        $this->assertSame(1,Render::count());
        Queue::assertNothingPushed();
    }

    public function test_caption_cannot_be_changed_after_approval(): void
    {
        Queue::fake(); Storage::fake('private');
        $admin=User::create(['username'=>'admin','password'=>'secret']);
        $channel=Channel::create(['peer_id'=>'-1001234567890','label'=>'Source','enabled'=>true]);
        $video=Video::create(['channel_id'=>$channel->id,'telegram_message_id'=>205,
            'title'=>'Overlay','caption_body'=>'Original','caption'=>'Original','status'=>'ready']);
        $render=Render::create(['video_id'=>$video->id,'version'=>1,'status'=>'ready','title'=>'Overlay',
            'caption'=>'Original','output_path'=>'outputs/205-v1.mp4','settings_snapshot'=>[]]);
        $video->update(['current_render_id'=>$render->id]);
        Publication::create(['video_id'=>$video->id,'render_id'=>$render->id,'destination'=>'@example',
            'status'=>'queued','approved_by'=>$admin->id]);
        $this->actingAs($admin)->post(route('videos.caption',$video),['caption_body'=>'Changed'])
            ->assertSessionHasErrors('caption_body');
        $this->assertSame('Original',$render->fresh()->caption);
        $this->assertSame('Original',$video->fresh()->caption_body);
        $this->actingAs($admin)->get(route('videos.show',$video))
            ->assertOk()->assertDontSee('name="caption_body"',false);
    }

    public function test_title_only_remake_uses_saved_options_and_keeps_caption(): void
    {
        Queue::fake(); Storage::fake('private');
        $admin=User::create(['username'=>'admin','password'=>'secret']);
        $channel=Channel::create(['peer_id'=>'-1001234567890','label'=>'Source','enabled'=>true]);
        $video=Video::create(['channel_id'=>$channel->id,'telegram_message_id'=>206,
            'source_path'=>'inputs/206.mp4','status'=>'ready','title'=>'Old title',
            'caption_body'=>'Already saved','caption'=>'Already saved',
            'overrides'=>['watermark_enabled'=>false]]);
        Storage::disk('private')->put('inputs/206.mp4',str_repeat('x',3000));
        $this->actingAs($admin)->post(route('videos.remake',$video),['title'=>'New overlay'])
            ->assertSessionHas('ok');
        $fresh=$video->fresh();
        $this->assertSame('New overlay',$fresh->title);
        $this->assertSame('Already saved',$fresh->caption_body);
        $this->assertSame('Already saved',$fresh->caption);
        $this->assertSame(false,$fresh->overrides['watermark_enabled']);
        $this->assertSame('queued',$fresh->status);
        Queue::assertPushed(ProcessVideo::class,1);
    }

    public function test_invalid_video_setting_shows_which_field_is_invalid(): void
    {
        Queue::fake(); Storage::fake('private');
        $admin=User::create(['username'=>'admin','password'=>'secret']);
        $channel=Channel::create(['peer_id'=>'-1001234567890','label'=>'Source','enabled'=>true]);
        $video=Video::create(['channel_id'=>$channel->id,'telegram_message_id'=>207,
            'source_path'=>'inputs/207.mp4','status'=>'ready']);
        Storage::disk('private')->put('inputs/207.mp4',str_repeat('x',3000));
        $this->actingAs($admin)->post(route('videos.remake',$video),[
            'title'=>'New title','settings'=>['font_size'=>['bad']]
        ])->assertSessionHasErrors('font_size');
        $this->assertSame('ready',$video->fresh()->status);
        Queue::assertNothingPushed();
    }

    public function test_caption_and_hashtags_cannot_exceed_telegram_limit(): void
    {
        Queue::fake();
        $admin=User::create(['username'=>'admin','password'=>'secret']);
        $channel=Channel::create(['peer_id'=>'-1001234567890','label'=>'Source','enabled'=>true]);
        $video=Video::create(['channel_id'=>$channel->id,'telegram_message_id'=>208,'status'=>'ready']);
        $render=Render::create(['video_id'=>$video->id,'version'=>1,'status'=>'ready',
            'caption'=>'Original','settings_snapshot'=>[]]);
        $video->update(['current_render_id'=>$render->id]);
        Setting::store('hashtags','#tag');
        $this->actingAs($admin)->post(route('videos.caption',$video),[
            'caption_body'=>str_repeat('x',1024)
        ])->assertSessionHasErrors('caption_body');
        $this->assertSame('Original',$render->fresh()->caption);
    }

}
