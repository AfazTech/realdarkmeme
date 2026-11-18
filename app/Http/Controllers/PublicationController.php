<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Jobs\PublishVideo;
use App\Models\{ActivityLog,Publication,Sender,Video};
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class PublicationController extends Controller
{
    public function approve(Video $video)
    {
        $publications=DB::transaction(function() use ($video) {
            $v=Video::query()->whereKey($video->id)->lockForUpdate()->firstOrFail();
            $render=$v->currentRender;
            if ($v->status!=='ready'||!$render||$render->status!=='ready')
                throw ValidationException::withMessages(['publish'=>'Only a ready version can be approved.']);
            if (!$render->output_path||!Storage::disk('private')->exists($render->output_path))
                throw ValidationException::withMessages(['publish'=>'Output file is missing.']);
            if (Publication::where('render_id',$render->id)->exists())
                throw ValidationException::withMessages(['publish'=>'This version has already been approved.']);
            $senders=Sender::query()->where('enabled',true)->whereNotNull('token')->where('destination','!=','')->get();
            if ($senders->isEmpty())
                throw ValidationException::withMessages(['publish'=>'Enable and configure at least one Sender in App Settings.']);
            $publications=[];
            foreach ($senders as $sender) {
                if ($sender->type==='telegram' && mb_strlen($render->caption)>1024)
                    throw ValidationException::withMessages(['publish'=>'Telegram caption exceeds 1,024 characters.']);
                $p=Publication::create([
                    'video_id'=>$v->id,'render_id'=>$render->id,'sender_id'=>$sender->id,
                    'destination'=>$sender->destination,'status'=>'queued','approved_by'=>auth()->id(),
                ]);
                $publications[]=$p;
                PublishVideo::dispatch($p->id)->onQueue('publish')->afterCommit();
            }
            return $publications;
        });
        ActivityLog::record('publish.approved','Video '.$video->id.' / '.count($publications).' senders',auth()->id());
        return back()->with('ok','Approved for '.count($publications).' enabled sender(s).');
    }

    public function retry(Request $request,Publication $publication)
    {
        $request->validate(['confirm_uncertain'=>'sometimes|accepted']);
        DB::transaction(function() use ($request,$publication) {
            $p=Publication::query()->whereKey($publication->id)->lockForUpdate()->firstOrFail();
            if (!in_array($p->status,['failed','uncertain'],true))
                throw ValidationException::withMessages(['publish'=>'This publication cannot be retried.']);
            if (!$p->sender || !$p->sender->enabled)
                throw ValidationException::withMessages(['publish'=>'Enable this Sender before retrying.']);
            if ($p->status==='uncertain'&&!$request->boolean('confirm_uncertain'))
                throw ValidationException::withMessages(['publish'=>'Confirm the duplicate-post risk after checking the destination.']);
            $p->update(['status'=>'queued','error'=>null]);
            PublishVideo::dispatch($p->id)->onQueue('publish')->afterCommit();
        });
        return back()->with('ok','Publication queued for retry.');
    }
}
