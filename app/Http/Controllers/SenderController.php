<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Jobs\PublishVideo;
use App\Models\{ActivityLog,Publication,Sender};
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;
use Neili\{Client,Settings};
use Throwable;

class SenderController extends Controller
{
    public function index()
    {
        return view('senders.index',['senders'=>Sender::query()->withCount('publications')->latest()->get()]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request,true);
        Sender::create($data+['enabled'=>true]);
        ActivityLog::record('sender.created',$data['name'],auth()->id());
        return back()->with('ok','Sender created.');
    }

    public function update(Request $request,Sender $sender)
    {
        $data = $this->validated($request,false);
        $data['enabled'] = $request->boolean('enabled');
        $sender->update($data);
        if ($sender->enabled) {
            // Jobs skipped while the sender was off must be requeued explicitly.
            $sender->publications()->where('status','queued')->pluck('id')->each(
                fn ($id) => PublishVideo::dispatch((int)$id)->onQueue('publish')
            );
        }
        ActivityLog::record('sender.updated','Sender '.$sender->id,auth()->id());
        return back()->with('ok','Sender settings saved.');
    }

    public function test(Sender $sender)
    {
        if ($sender->type !== 'telegram' || !$sender->token) {
            return back()->withErrors(['sender'=>'Configure the Telegram bot token first.']);
        }
        try {
            $settings=(new Settings())->setAccessToken($sender->token)->setApiUrl((string)$sender->api_url)->setTimeout(20,8);
            $result=(new Client($settings))->getMe()->await();
            $username=$result['result']['username']??$result['username']??'bot';
            return back()->with('ok','Bot identity confirmed: @'.$username.'. Channel posting rights were not tested.');
        } catch(Throwable $e) {
            report($e);
            return back()->withErrors(['sender'=>'Bot test failed. Check the Laravel log.']);
        }
    }

    private function validated(Request $request,bool $creating): array
    {
        $data=$request->validate([
            'name'=>'required|string|max:120',
            'type'=>'required|in:telegram',
            'destination'=>['required','string','max:120','regex:/^(?:@[A-Za-z0-9_]{5,32}|-100[0-9]{5,20})$/D'],
            'api_url'=>'required|string|max:255',
            'token'=>[$creating?'required':'nullable','string','max:220'],
        ]);
        $data['api_url']=rtrim(trim($data['api_url']),'/');
        $api=$data['api_url'];
        if (!preg_match('~^https://[A-Za-z0-9.-]+(?::[0-9]{1,5})?(?:/[A-Za-z0-9/_-]+)?/bot$~D',$api)
            || !in_array(strtolower((string)parse_url($api,PHP_URL_HOST)),config('realdarkmeme.bot_api_hosts',[]),true)) {
            throw ValidationException::withMessages(['api_url'=>'Bot API host is not permitted or the URL is invalid.']);
        }
        $token=trim((string)($data['token']??''));
        if ($token!=='' && !preg_match('/^[0-9]{5,20}:[A-Za-z0-9_-]{20,200}$/D',$token)) {
            throw ValidationException::withMessages(['token'=>'Invalid Telegram bot token format.']);
        }
        if ($token==='') unset($data['token']);
        return $data;
    }
}
