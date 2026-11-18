<?php
declare(strict_types=1);

namespace App\Jobs;

use App\Models\{ActivityLog,Publication};
use App\Services\Senders\SenderManager;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/** Never automatically retry a possible delivery to avoid duplicate posts. */
class PublishVideo implements ShouldQueue
{
    use Queueable;
    public int $tries=1;
    public int $timeout=1800;

    public function __construct(public int $publicationId) { $this->onQueue('publish'); }

    public function handle(SenderManager $manager): void
    {
        $publication=DB::transaction(function() {
            $p=Publication::query()->lockForUpdate()->find($this->publicationId);
            if (!$p||$p->status!=='queued') return null;
            if (!$p->sender || !$p->sender->enabled) return null;
            $p->update(['status'=>'sending','attempts'=>$p->attempts+1,'started_at'=>now(),'error'=>null]);
            return $p;
        });
        if (!$publication) return;
        $attempted=false;
        try {
            $sender=$publication->sender;
            if (!$sender || !$sender->enabled || !$sender->token) throw new RuntimeException('Sender is disabled or missing credentials.');
            $render=$publication->render;
            if (!$render || $render->status!=='ready'||!$render->output_path||!Storage::disk('private')->exists($render->output_path))
                throw new RuntimeException('Rendered output does not exist.');
            $path=Storage::disk('private')->path($render->output_path);
            $attempted=true;
            $messageId=$manager->send($sender,$path,(string)$render->caption,(string)$publication->destination);
            $publication->update(['status'=>'sent','telegram_message_id'=>$sender->type==='telegram'?$messageId:null,'sent_at'=>now(),'error'=>null]);
            ActivityLog::record('publish.sent','Publication '.$publication->id.' / sender '.$sender->id);
        } catch (Throwable $e) {
            $message=mb_substr($e->getMessage(),0,900);
            $token=$publication->sender?->token??'';
            if ($token!=='') $message=str_replace($token,'[REDACTED]',$message);
            $publication->update(['status'=>$attempted?'uncertain':'failed','error'=>$message]);
            ActivityLog::record('publish.'.($attempted?'uncertain':'failed'),'Publication '.$publication->id);
        }
    }
}
