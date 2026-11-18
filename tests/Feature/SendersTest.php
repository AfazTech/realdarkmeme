<?php
declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\PublishVideo;
use App\Models\{Channel,Publication,Render,Sender,User,Video};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Queue,Storage};
use Tests\TestCase;

class SendersTest extends TestCase
{
    use RefreshDatabase;

    public function test_source_channel_needs_no_username_or_identifier_column(): void
    {
        $admin=User::create(['username'=>'admin','password'=>'x']);
        $this->actingAs($admin)->post('/channels',['peer_id'=>'-1001234567890','label'=>'Source'])->assertSessionHas('ok');
        $this->assertDatabaseHas('channels',['peer_id'=>'-1001234567890']);
        $this->actingAs($admin)->get('/channels')->assertOk()->assertDontSee('name="identifier"',false)->assertDontSee('Username / identifier');
    }

    public function test_approving_queues_only_enabled_senders_and_supports_multiple_destinations(): void
    {
        Queue::fake(); Storage::fake('private');
        $admin=User::create(['username'=>'admin','password'=>'x']);
        $channel=Channel::create(['peer_id'=>'-1001234567890','label'=>'Source','enabled'=>true]);
        $v=Video::create(['channel_id'=>$channel->id,'telegram_message_id'=>1,'status'=>'ready']);
        $render=Render::create(['video_id'=>$v->id,'version'=>1,'status'=>'ready','output_path'=>'outputs/1.mp4','caption'=>'Hello','settings_snapshot'=>[]]);
        $v->update(['current_render_id'=>$render->id]);
        Storage::disk('private')->put('outputs/1.mp4',str_repeat('x',2000));
        foreach ([1,2,3] as $number) Sender::create([
            'name'=>'Sender '.$number,'type'=>'telegram','enabled'=>$number!==3,
            'token'=>'123456789:ABCDEFGHIJKLMNOPQRSTUVWXYZ123456',
            'destination'=>'@destination'.$number,'api_url'=>'https://api.telegram.org/bot',
        ]);
        $this->actingAs($admin)->post(route('videos.approve',$v))->assertSessionHas('ok');
        $this->assertSame(2,Publication::count());
        $this->assertSame(2,Publication::distinct()->count('sender_id'));
        Queue::assertPushed(PublishVideo::class,2);
        $this->actingAs($admin)->post(route('videos.approve',$v))->assertSessionHasErrors('publish');
    }

    public function test_sender_token_is_encrypted_and_never_rendered(): void
    {
        $admin=User::create(['username'=>'admin','password'=>'x']);
        $token='123456789:ABCDEFGHIJKLMNOPQRSTUVWXYZ123456';
        $sender=Sender::create(['name'=>'Main','type'=>'telegram','enabled'=>true,'token'=>$token,'destination'=>'@destination','api_url'=>'https://api.telegram.org/bot']);
        $this->assertNotSame($token,\DB::table('senders')->where('id',$sender->id)->value('token'));
        $this->actingAs($admin)->get(route('senders.index'))->assertOk()->assertSee('Add Telegram Sender')->assertDontSee($token);
        $this->actingAs($admin)->patch(route('senders.update',$sender),['name'=>'Main','type'=>'telegram','destination'=>'@destination','api_url'=>'https://api.telegram.org/bot'])
            ->assertSessionHas('ok');
        $this->assertFalse($sender->fresh()->enabled);
    }
}
