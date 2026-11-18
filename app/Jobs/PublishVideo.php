<?php
namespace App\Jobs;
use App\Models\{Publication,ActivityLog};
use App\Support\BotSettings;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Neili\{Client,Settings,Media};
use Throwable;
use RuntimeException;

/** Never auto-retry an upload if Telegram may have received it. */
class PublishVideo implements ShouldQueue
{
 use Queueable;
 public int $tries=1;
 public int $timeout=1800;
 public function __construct(public int $publicationId) { $this->onQueue('publish'); }
 public function handle(): void
 {
    $publication=DB::transaction(function(){
        $p=Publication::query()->lockForUpdate()->find($this->publicationId);
        if(!$p||$p->status!=='queued')return null;
        $p->update(['status'=>'sending','attempts'=>$p->attempts+1,'started_at'=>now(),'error'=>null]);
        return $p;
    });
    if(!$publication)return;
    $attempted=false;
    try {
        BotSettings::assertReady();
        $render=$publication->render;
        if(!$render||$render->status!=='ready'||!$render->output_path||!Storage::disk('private')->exists($render->output_path))
           throw new RuntimeException('Rendered output does not exist');
        if(mb_strlen($render->caption)>1024)throw new RuntimeException('Caption is longer than Telegram limit (1024 characters)');
        $path=Storage::disk('private')->path($render->output_path);
        if(BotSettings::apiUrl()==='https://api.telegram.org/bot' && filesize($path)>50*1024*1024)
           throw new RuntimeException('Video exceeds standard Bot API upload limit. Configure a local Bot API server.');
        $token=BotSettings::token();
        $config=(new Settings())->setAccessToken($token)->setApiUrl(BotSettings::apiUrl())->setTimeout(900,15);
        $client=new Client($config);
        $attempted=true;
        $result=$client->sendVideo((string)$publication->destination,new Media($path),caption:$render->caption)->await();
        $payload=$result['result']??$result;
        if(!is_array($payload)||!is_numeric($payload['message_id']??null))throw new RuntimeException('Telegram response did not include message ID');
        $publication->update(['status'=>'sent','telegram_message_id'=>(int)$payload['message_id'],'sent_at'=>now(),'error'=>null]);
        ActivityLog::record('publish.sent','Publication '.$publication->id.' / message '.$payload['message_id']);
    } catch(Throwable $e){
        try { $token=BotSettings::token(); }catch(Throwable){ $token=''; }
        $message=mb_substr($e->getMessage(),0,900);
        if($token!=='')$message=str_replace($token,'[REDACTED]', $message);
        $publication->update(['status'=>$attempted?'uncertain':'failed','error'=>$message]);
        ActivityLog::record('publish.'.($attempted?'uncertain':'failed'),'Publication '.$publication->id.' / '.mb_substr($message,0,300));
    }
 }
}
