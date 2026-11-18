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
            ->assertOk()->assertSee('Media Assets');
        $this->actingAs($admin)->get(route('settings.media'))
            ->assertOk()->assertSee('No image configured yet');
        // A tiny PNG fixture; previews never expose a public storage path.
        $png=base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQIHWP4z8DwHwAFgAI/ScL/nwAAAABJRU5ErkJggg==');
        Storage::disk('private')->put('assets/thumbnail.png',$png);
        $this->actingAs($admin)->get(route('settings.media'))
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
            ->assertOk()->assertSee('App Settings')->assertSee('Senders')->assertSee('Caption Defaults');
        $this->actingAs($admin)->get(route('settings.captions'))->assertOk()->assertSee('Default hashtags');
        $this->actingAs($admin)->get(route('senders.index'))->assertOk()->assertSee('Add Telegram Sender');
        $this->actingAs($admin)->get(route('settings.security'))->assertOk()->assertSee('Allowed admin IP addresses');
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


    public function test_remake_accepts_blank_optional_font_paths_from_html_form(): void
    {
        Queue::fake(); Storage::fake('private');
        $admin=User::create(['username'=>'admin','password'=>'secret']);
        $channel=Channel::create(['peer_id'=>'-1001234567890','label'=>'Source','enabled'=>true]);
        $video=Video::create([
            'channel_id'=>$channel->id,'telegram_message_id'=>209,
            'source_path'=>'inputs/209.mp4','status'=>'ready','title'=>'Old overlay',
        ]);
        Storage::disk('private')->put('inputs/209.mp4',str_repeat('x',3000));

        // Empty HTML fields become null via Laravel's ConvertEmptyStringsToNull middleware.
        $settings=array_replace(RenderSettings::current(),[
            'title_font'=>'',
            'emoji_font'=>'',
            'watermark_font'=>'',
        ]);
        $this->actingAs($admin)->post(route('videos.remake',$video),[
            'title'=>'New overlay','settings'=>$settings,
        ])->assertSessionHas('ok')->assertSessionHasNoErrors();

        $this->assertSame('queued',$video->fresh()->status);
        $this->assertSame('New overlay',$video->fresh()->title);
        Queue::assertPushed(ProcessVideo::class,1);
    }

    public function test_studio_settings_accept_empty_font_fields(): void
    {
        $admin=User::create(['username'=>'admin','password'=>'secret']);
        $settings=array_replace(RenderSettings::current(),[
            'title_font'=>'','emoji_font'=>'','watermark_font'=>'',
        ]);
        $this->actingAs($admin)->post(route('settings.render'),[
            'settings'=>$settings,
        ])->assertSessionHas('ok')->assertSessionHasNoErrors();

        $saved=json_decode((string)Setting::fetch('render_settings'),true);
        $this->assertSame('',$saved['title_font']);
        $this->assertSame('',$saved['emoji_font']);
        $this->assertSame('',$saved['watermark_font']);
    }

    public function test_null_font_paths_are_valid_but_arrays_remain_invalid(): void
    {
        $settings=array_replace(RenderSettings::current(),[
            'title_font'=>null,'emoji_font'=>null,'watermark_font'=>null,
        ]);
        $validated=RenderSettings::validate($settings);
        $this->assertSame('',$validated['title_font']);
        $this->assertSame('',$validated['emoji_font']);
        $this->assertSame('',$validated['watermark_font']);

        $settings['title_font']=['unexpected'];
        try {
            RenderSettings::validate($settings);
            $this->fail('Expected a validation error for an array font path.');
        } catch (\Illuminate\Validation\ValidationException $exception) {
            $this->assertArrayHasKey('title_font',$exception->errors());
        }
    }


    public function test_settings_sections_are_independent(): void
    {
        $admin=User::create(['username'=>'admin','password'=>'secret']);
        $original=RenderSettings::current();
        $this->actingAs($admin)->get(route('settings.group','watermark'))->assertOk()->assertSee('Watermark Settings');
        $this->actingAs($admin)->get(route('settings.group','cover'))->assertOk()->assertSee('Cover Settings');
        $this->actingAs($admin)->post(route('settings.group.save','cover'),[
            'settings'=>['font_size'=>'93','title_font'=>''],
        ])->assertSessionHas('ok')->assertSessionHasNoErrors();
        $saved=RenderSettings::current();
        $this->assertSame(93,$saved['font_size']);
        $this->assertSame('',$saved['title_font']);
        $this->assertSame($original['watermark_size'],$saved['watermark_size']);
        $this->assertSame($original['outro_enabled'],$saved['outro_enabled']);
        $this->actingAs($admin)->post(route('settings.group.save','cover'),[
            'settings'=>['watermark_size'=>12],
        ])->assertSessionHasErrors('settings');
        $this->assertSame($original['watermark_size'],RenderSettings::current()['watermark_size']);
    }

    public function test_admin_ip_allowlist_checks_current_ip_before_enabling(): void
    {
        $admin=User::create(['username'=>'admin','password'=>'secret']);
        $this->withServerVariables(['REMOTE_ADDR'=>'192.0.2.40']);
        $this->actingAs($admin)->post(route('settings.security.allowlist'),[
            'enabled'=>'1','ips'=>'203.0.113.10','current_password'=>'secret',
        ])->assertSessionHasErrors('ips');
        $this->assertSame('',Setting::fetch('admin_ip_allowlist',''));
        $this->actingAs($admin)->post(route('settings.security.allowlist'),[
            'enabled'=>'1','ips'=>"192.0.2.0/24\n2001:db8::/64",
            'current_password'=>'secret',
        ])->assertSessionHas('ok')->assertSessionHasNoErrors();
        $this->assertStringContainsString('192.0.2.0/24',(string)Setting::fetch('admin_ip_allowlist'));
        auth()->logout();
        $this->withServerVariables(['REMOTE_ADDR'=>'192.0.2.123']);
        $this->get(route('login'))->assertOk();
        $this->withServerVariables(['REMOTE_ADDR'=>'198.51.100.11']);
        $this->get(route('login'))->assertForbidden();
        $this->get(route('dashboard'))->assertForbidden();
    }

    public function test_admin_can_disable_ip_allowlist_with_password(): void
    {
        $admin=User::create(['username'=>'admin','password'=>'secret']);
        Setting::store('admin_ip_allowlist','127.0.0.1');
        $this->actingAs($admin)->withServerVariables(['REMOTE_ADDR'=>'127.0.0.1']);
        $this->post(route('settings.security.allowlist'),[
            'enabled'=>'0','ips'=>'127.0.0.1','current_password'=>'wrong',
        ])->assertSessionHasErrors('current_password');
        $this->assertSame('127.0.0.1',Setting::fetch('admin_ip_allowlist'));
        $this->post(route('settings.security.allowlist'),[
            'enabled'=>'0','ips'=>'127.0.0.1','current_password'=>'secret',
        ])->assertSessionHas('ok');
        $this->assertSame('',Setting::fetch('admin_ip_allowlist'));
    }

    public function test_admin_ip_matching_supports_ipv4_and_ipv6_cidr(): void
    {
        $allow=\App\Support\AdminIpAllowlist::class;
        $this->assertTrue($allow::matches('192.0.2.0/24','192.0.2.254'));
        $this->assertFalse($allow::matches('192.0.2.0/24','192.0.3.1'));
        $this->assertTrue($allow::matches('2001:db8::/64','2001:db8::beef'));
        $this->assertFalse($allow::matches('2001:db8::/64','2001:db8:1::1'));
        $this->assertFalse($allow::valid('0.0.0.0/0'));
        $this->assertFalse($allow::valid('::/0'));
        $this->assertFalse($allow::valid('example.com'));
    }

}
